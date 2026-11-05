<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_learnmetrics\local;

/**
 * Turns the standard log into learning time per user, course and day.
 *
 * The time between two consecutive actions of a user counts towards the course of the first action, when
 * the gap is no longer than the session gap; a longer gap starts a new session. The last action of each
 * user is kept, so a session that spans two runs is counted too. Log entries are read in batches, in id
 * order, from where the previous run stopped.
 *
 * "Still here" pings, sent every minute by pages of a course while the learner uses them, are activity points
 * too: they bridge the gaps of a long page (a SCORM package, a video) but are not counted as actions.
 *
 * @package    local_learnmetrics
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class time_tracker {
    /** @var int Default longest gap between two actions of one session, in seconds. */
    const DEFAULT_SESSION_GAP = 30 * MINSECS;

    /** @var int Default number of days of existing logs read on the first run. */
    const DEFAULT_BACKFILL_DAYS = 90;

    /** @var int Log entries and pings newer than this many seconds wait for the next run. */
    const SAFETY_MARGIN = 2 * MINSECS;

    /**
     * @var int Longest pause without pings that counts in full after an action in a web browser, in seconds. Pages
     * ping every minute while the learner uses them, so a longer silence means the learner was away or the page hidden.
     */
    const PING_GAP = 3 * MINSECS;

    /** @var int Longest gap between two actions of one session, in seconds. */
    protected $gap;

    /** @var int Since when pages send pings, so silences count (0: pings are off and the session gap alone applies). */
    protected $pingsince;

    /** @var int The current time. */
    protected $now;

    /** @var int Log entries read per batch. */
    protected $batchsize;

    /** @var \DateTimeZone */
    protected $timezone;

    /** @var array Day start by date, to avoid recomputing it. */
    protected $daystarts = [];

    /**
     * Constructor.
     *
     * @param int|null $gap Longest gap between two actions of one session, in seconds. Defaults to the setting.
     * @param int $batchsize Log entries read per batch.
     * @param int|null $now The current time; now by default.
     */
    public function __construct(?int $gap = null, int $batchsize = 20000, ?int $now = null) {
        $setting = (int)get_config('local_learnmetrics', 'sessiongap');
        $this->gap = $gap ?? ($setting > 0 ? $setting : self::DEFAULT_SESSION_GAP);
        $this->batchsize = max(1, $batchsize);
        $this->now = $now ?? time();
        $this->timezone = \core_date::get_server_timezone_object();
        $this->pingsince = get_config('local_learnmetrics', 'heartbeat') === '0' ? 0
            : (int)get_config('local_learnmetrics', 'pingsince');
    }

    /**
     * Process new log entries and "still here" pings.
     *
     * Both are activity points of a session and are merged in time order. They are processed up to a safe cutoff
     * (a little before now, so entries still being written are not skipped), and, while there are more log entries
     * than fit in a batch, only up to the last log entry of the batch, so pings are never processed ahead of the
     * log. Processed pings are deleted.
     *
     * @param int $maxbatches Stop after this many batches; the next run continues.
     * @return int Number of log entries read.
     */
    public function process(int $maxbatches = 25): int {
        global $DB;
        $lastid = $this->get_start_id();
        $lastpingid = (int)get_config('local_learnmetrics', 'lastpingid');
        $cutoff = $this->now - self::SAFETY_MARGIN;
        $read = 0;
        for ($batch = 0; $batch < $maxbatches; $batch++) {
            $logs = $DB->get_records_select(
                'logstore_standard_log',
                'id > :lastid',
                ['lastid' => $lastid],
                'id',
                'id, userid, courseid, contextlevel, contextinstanceid, timecreated, origin, realuserid, anonymous',
                0,
                $this->batchsize
            );
            $more = count($logs) == $this->batchsize;
            // Stop at the first entry after the cutoff: the next run starts from there.
            foreach ($logs as $id => $log) {
                if ($log->timecreated > $cutoff) {
                    $logs = array_filter($logs, fn($entry) => $entry->id < $id);
                    $more = false;
                    break;
                }
            }
            $until = $more && $logs ? (int)end($logs)->timecreated : $cutoff;
            $pings = $DB->get_records_select(
                'local_learnmetrics_ping',
                'id > :lastpingid AND timecreated <= :until',
                ['lastpingid' => $lastpingid, 'until' => $until],
                'id',
                'id, userid, courseid, timecreated'
            );
            if (!$logs && !$pings) {
                break;
            }
            $transaction = $DB->start_delegated_transaction();
            $this->process_batch($logs, $pings);
            if ($logs) {
                $lastid = (int)array_key_last($logs);
                set_config('lastlogid', $lastid, 'local_learnmetrics');
            }
            if ($pings) {
                $lastpingid = (int)array_key_last($pings);
                set_config('lastpingid', $lastpingid, 'local_learnmetrics');
                $DB->delete_records_select('local_learnmetrics_ping', 'id <= :lastpingid', ['lastpingid' => $lastpingid]);
            }
            $transaction->allow_commit();
            $read += count($logs);
            if (!$more) {
                break;
            }
        }
        return $read;
    }

    /**
     * The id after which to read, starting the first run at the backfill period.
     *
     * @return int
     */
    protected function get_start_id(): int {
        global $DB;
        $lastid = get_config('local_learnmetrics', 'lastlogid');
        if ($lastid !== false) {
            return (int)$lastid;
        }
        $days = get_config('local_learnmetrics', 'backfilldays');
        $days = $days === false ? self::DEFAULT_BACKFILL_DAYS : (int)$days;
        $first = $DB->get_field_select(
            'logstore_standard_log',
            'MIN(id)',
            'timecreated >= :since',
            ['since' => time() - $days * DAYSECS]
        );
        $start = $first ? (int)$first - 1 : (int)$DB->get_field_sql('SELECT MAX(id) FROM {logstore_standard_log}');
        set_config('lastlogid', $start, 'local_learnmetrics');
        return $start;
    }

    /**
     * Add one batch of log entries and pings to the daily and course totals.
     *
     * Log entries and pings are activity points of a session alike, taken in time order; only log entries are
     * counted as actions.
     *
     * @param \stdClass[] $logs In id order.
     * @param \stdClass[] $pings In id order.
     */
    protected function process_batch(array $logs, array $pings = []): void {
        global $CFG;
        $points = [];
        foreach ($logs as $log) {
            $userid = (int)$log->userid;
            if (
                $userid <= 0 || $userid == $CFG->siteguest || $log->anonymous || $log->origin === 'cli'
                    || $log->origin === 'restore' || !empty($log->realuserid)
            ) {
                // Not a person learning: system actions, guests, anonymous posts and "log in as".
                continue;
            }
            // Whether the action came from a web browser, which pings; the mobile app does not.
            $web = $log->origin === 'web' ? 1 : 0;
            // The activity of the action, if it was in one.
            $cmid = (int)$log->contextlevel === CONTEXT_MODULE ? (int)$log->contextinstanceid : 0;
            $points[] = [(int)$log->timecreated, 0, (int)$log->id, $userid, (int)$log->courseid ?: SITEID, 1, $web, $cmid];
        }
        foreach ($pings as $ping) {
            // A ping does not say which activity: -1 continues the activity of the last action, see below.
            $points[] = [(int)$ping->timecreated, 1, (int)$ping->id, (int)$ping->userid, (int)$ping->courseid, 0, 1, -1];
        }
        // In time order; at the same second, log entries first, then in id order.
        sort($points);

        $sessions = $this->load_sessions(array_unique(array_column($points, 3)));
        $daily = [];
        $cmdaily = [];
        $activity = [];
        foreach ($points as [$time, , , $userid, $courseid, $events, $web, $cmid]) {
            if ($cmid < 0) {
                // A ping continues the activity of the last action in the same course, such as a SCORM package.
                $last = $sessions[$userid] ?? null;
                $cmid = $last && (int)$last->courseid === $courseid ? (int)($last->cmid ?? 0) : 0;
            }
            $this->add($daily, $userid, $courseid, $this->day_start($time), 0, $events);
            if ($cmid && $events) {
                $this->add_activity($cmdaily, $userid, $courseid, $cmid, $this->day_start($time), 0, $events);
            }
            $activity["$userid-$courseid"] = [
                'first' => min($activity["$userid-$courseid"]['first'] ?? PHP_INT_MAX, $time),
                'last' => max($activity["$userid-$courseid"]['last'] ?? 0, $time),
            ];

            if (isset($sessions[$userid])) {
                $previous = $sessions[$userid];
                $gap = $time - $previous->timecreated;
                if ($gap >= 0 && $gap <= $this->gap) {
                    if (
                        $this->pingsince && !empty($previous->web) && $previous->timecreated >= $this->pingsince
                            && $gap > self::PING_GAP
                    ) {
                        // A browser that sent no pings for this long was hidden or not used: count only a short pause.
                        $gap = self::PING_GAP;
                    }
                    $this->add($daily, $userid, (int)$previous->courseid, $this->day_start((int)$previous->timecreated), $gap, 0);
                    if (!empty($previous->cmid)) {
                        $this->add_activity(
                            $cmdaily,
                            $userid,
                            (int)$previous->courseid,
                            (int)$previous->cmid,
                            $this->day_start((int)$previous->timecreated),
                            $gap,
                            0
                        );
                    }
                }
            } else {
                $sessions[$userid] = (object)['userid' => $userid];
            }
            $sessions[$userid]->courseid = $courseid;
            $sessions[$userid]->timecreated = $time;
            $sessions[$userid]->web = $web;
            $sessions[$userid]->cmid = $cmid;
            $sessions[$userid]->changed = true;
        }
        $this->save_daily($daily);
        $this->save_activity($cmdaily);
        $this->save_sessions($sessions);
        $this->update_course_totals(array_keys($activity), $activity);
    }

    /**
     * Add time and actions to the daily totals of this batch.
     *
     * @param array $daily
     * @param int $userid
     * @param int $courseid
     * @param int $day
     * @param int $seconds
     * @param int $events
     */
    protected function add(array &$daily, int $userid, int $courseid, int $day, int $seconds, int $events): void {
        $key = "$userid-$courseid-$day";
        if (!isset($daily[$key])) {
            $daily[$key] = ['userid' => $userid, 'courseid' => $courseid, 'day' => $day, 'timespent' => 0, 'events' => 0];
        }
        $daily[$key]['timespent'] += $seconds;
        $daily[$key]['events'] += $events;
    }

    /**
     * Add time and actions to the daily totals per activity of this batch.
     *
     * @param array $cmdaily
     * @param int $userid
     * @param int $courseid
     * @param int $cmid
     * @param int $day
     * @param int $seconds
     * @param int $events
     */
    protected function add_activity(
        array &$cmdaily,
        int $userid,
        int $courseid,
        int $cmid,
        int $day,
        int $seconds,
        int $events
    ): void {
        $key = "$userid-$cmid-$day";
        if (!isset($cmdaily[$key])) {
            $cmdaily[$key] = ['userid' => $userid, 'courseid' => $courseid, 'cmid' => $cmid, 'day' => $day, 'timespent' => 0,
                'events' => 0];
        }
        $cmdaily[$key]['timespent'] += $seconds;
        $cmdaily[$key]['events'] += $events;
    }

    /**
     * The start of the day a time falls on, in the site time zone.
     *
     * @param int $time
     * @return int
     */
    public function day_start(int $time): int {
        $date = (new \DateTime('@' . $time))->setTimezone($this->timezone);
        $key = $date->format('Y-m-d');
        if (!isset($this->daystarts[$key])) {
            $this->daystarts[$key] = $date->setTime(0, 0)->getTimestamp();
        }
        return $this->daystarts[$key];
    }

    /**
     * The last action of each user before this batch.
     *
     * @param int[] $userids
     * @return \stdClass[] By user id.
     */
    protected function load_sessions(array $userids): array {
        global $DB;
        $sessions = [];
        foreach (array_chunk($userids, 1000) as $chunk) {
            [$insql, $params] = $DB->get_in_or_equal($chunk);
            foreach ($DB->get_records_select('local_learnmetrics_session', "userid $insql", $params) as $session) {
                $sessions[$session->userid] = $session;
            }
        }
        return $sessions;
    }

    /**
     * Save the last action of each user seen in this batch.
     *
     * @param \stdClass[] $sessions
     */
    protected function save_sessions(array $sessions): void {
        global $DB;
        foreach ($sessions as $session) {
            if (empty($session->changed)) {
                continue;
            }
            unset($session->changed);
            if (isset($session->id)) {
                $DB->update_record('local_learnmetrics_session', $session);
            } else {
                $DB->insert_record('local_learnmetrics_session', $session);
            }
        }
    }

    /**
     * Add this batch's daily totals to the stored ones.
     *
     * @param array $daily
     */
    protected function save_daily(array $daily): void {
        global $DB;
        if (!$daily) {
            return;
        }
        // Read the stored rows these totals add to, by user, from the earliest day in the batch.
        $mindday = min(array_column($daily, 'day'));
        $existing = [];
        foreach (array_chunk(array_unique(array_column($daily, 'userid')), 1000) as $chunk) {
            [$insql, $params] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED);
            $params['day'] = $mindday;
            $rows = $DB->get_records_select('local_learnmetrics_daily', "userid $insql AND day >= :day", $params);
            foreach ($rows as $row) {
                $existing["{$row->userid}-{$row->courseid}-{$row->day}"] = $row;
            }
        }
        foreach ($daily as $key => $totals) {
            if (isset($existing[$key])) {
                $row = $existing[$key];
                $row->timespent += $totals['timespent'];
                $row->events += $totals['events'];
                $DB->update_record('local_learnmetrics_daily', $row);
            } else {
                $DB->insert_record('local_learnmetrics_daily', (object)$totals);
            }
        }
    }

    /**
     * Add this batch's daily totals per activity to the stored ones.
     *
     * @param array $cmdaily
     */
    protected function save_activity(array $cmdaily): void {
        global $DB;
        if (!$cmdaily) {
            return;
        }
        $minday = min(array_column($cmdaily, 'day'));
        $existing = [];
        foreach (array_chunk(array_unique(array_column($cmdaily, 'userid')), 1000) as $chunk) {
            [$insql, $params] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED);
            $params['day'] = $minday;
            $rows = $DB->get_records_select('local_learnmetrics_cmtime', "userid $insql AND day >= :day", $params);
            foreach ($rows as $row) {
                $existing["{$row->userid}-{$row->cmid}-{$row->day}"] = $row;
            }
        }
        foreach ($cmdaily as $key => $totals) {
            if (isset($existing[$key])) {
                $row = $existing[$key];
                $row->timespent += $totals['timespent'];
                $row->events += $totals['events'];
                $DB->update_record('local_learnmetrics_cmtime', $row);
            } else {
                $DB->insert_record('local_learnmetrics_cmtime', (object)$totals);
            }
        }
    }

    /**
     * Recalculate the course totals of the users and courses in this batch.
     *
     * @param string[] $keys "userid-courseid"
     * @param array $activity First and last action time by key, in this batch.
     */
    protected function update_course_totals(array $keys, array $activity): void {
        global $DB;
        // Time can also be added to the course of a previous batch's last action, so include those pairs.
        $pairs = [];
        foreach ($keys as $key) {
            [$userid, $courseid] = explode('-', $key);
            $pairs[(int)$userid][(int)$courseid] = true;
        }
        foreach (array_chunk(array_keys($pairs), 500, true) as $userids) {
            [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
            $sums = $DB->get_recordset_sql(
                "SELECT " . $DB->sql_concat('userid', "'-'", 'courseid') . " AS pairkey, userid, courseid,
                        SUM(timespent) AS timespent, SUM(events) AS events, COUNT(1) AS activedays
                   FROM {local_learnmetrics_daily}
                  WHERE userid $insql
               GROUP BY userid, courseid",
                $params
            );
            $totals = [];
            foreach ($sums as $sum) {
                $totals["{$sum->userid}-{$sum->courseid}"] = $sum;
            }
            $sums->close();
            $stored = [];
            foreach ($DB->get_records_select('local_learnmetrics_course', "userid $insql", $params) as $row) {
                $stored["{$row->userid}-{$row->courseid}"] = $row;
            }
            foreach ($totals as $key => $sum) {
                $row = $stored[$key] ?? (object)['userid' => $sum->userid, 'courseid' => $sum->courseid,
                    'firstactivity' => 0, 'lastactivity' => 0];
                if (
                    isset($row->id) && $row->timespent == $sum->timespent && $row->events == $sum->events
                        && !isset($activity[$key])
                ) {
                    continue;
                }
                $row->timespent = (int)$sum->timespent;
                $row->events = (int)$sum->events;
                $row->activedays = (int)$sum->activedays;
                if (isset($activity[$key])) {
                    $row->firstactivity = $row->firstactivity ? min($row->firstactivity, $activity[$key]['first'])
                        : $activity[$key]['first'];
                    $row->lastactivity = max($row->lastactivity, $activity[$key]['last']);
                }
                if (isset($row->id)) {
                    $DB->update_record('local_learnmetrics_course', $row);
                } else {
                    $DB->insert_record('local_learnmetrics_course', $row);
                }
            }
        }
    }
}
