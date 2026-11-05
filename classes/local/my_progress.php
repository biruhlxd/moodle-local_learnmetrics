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
 * The progress of one learner: learning time and courses.
 *
 * @package    local_learnmetrics
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class my_progress {
    /** @var int The learner. */
    protected $userid;

    /** @var int Start of the period. */
    protected $from;

    /** @var int End of the period. */
    protected $to;

    /**
     * Constructor.
     *
     * @param int $userid The learner.
     * @param int $days Length of the period, ending now.
     * @param int|null $now The end of the period; now by default.
     */
    public function __construct(int $userid, int $days, ?int $now = null) {
        $tracker = new time_tracker();
        $this->userid = $userid;
        $this->to = $now ?? time();
        $this->from = $tracker->day_start($this->to - ($days - 1) * DAYSECS);
    }

    /**
     * Whether the current user can see the progress of a user: their own, or another user's with the capability in
     * that user's context (managers, or a parent or mentor role).
     *
     * @param int $userid
     * @return bool
     */
    public static function can_view(int $userid): bool {
        global $USER;
        if ($userid == $USER->id) {
            return !isguestuser() && has_capability('local/learnmetrics:viewownprogress', \context_system::instance());
        }
        $context = \context_user::instance($userid, IGNORE_MISSING);
        return $context && has_capability('local/learnmetrics:viewuserprogress', $context);
    }

    /**
     * Headline numbers.
     *
     * @return array hours and activedays in the period, totalhours ever
     */
    public function get_summary(): array {
        global $DB;
        $period = $DB->get_record_sql(
            "SELECT COUNT(DISTINCT d.day) AS days, COALESCE(SUM(d.timespent), 0) AS seconds
               FROM {local_learnmetrics_daily} d
              WHERE d.userid = :userid AND d.day >= :from AND d.day <= :to AND d.timespent > 0",
            ['userid' => $this->userid, 'from' => $this->from, 'to' => $this->to]
        );
        $total = $DB->get_field_sql(
            "SELECT COALESCE(SUM(d.timespent), 0) FROM {local_learnmetrics_daily} d WHERE d.userid = :userid",
            ['userid' => $this->userid]
        );
        return [
            'hours' => round($period->seconds / HOURSECS, 1),
            'activedays' => (int)$period->days,
            'totalhours' => round($total / HOURSECS, 1),
        ];
    }

    /**
     * Learning hours on each day of the period.
     *
     * @return float[] Day start => hours, for every day, including quiet ones.
     */
    public function get_daily_hours(): array {
        global $DB;
        $seconds = $DB->get_records_sql_menu(
            "SELECT d.day, SUM(d.timespent)
               FROM {local_learnmetrics_daily} d
              WHERE d.userid = :userid AND d.day >= :from AND d.day <= :to
           GROUP BY d.day",
            ['userid' => $this->userid, 'from' => $this->from, 'to' => $this->to]
        );
        $hours = [];
        $tracker = new time_tracker();
        for ($day = $this->from; $day <= $this->to; $day = $tracker->day_start($day + DAYSECS + 2 * HOURSECS)) {
            $hours[$day] = isset($seconds[$day]) ? round($seconds[$day] / HOURSECS, 1) : 0;
        }
        return $hours;
    }

    /**
     * The courses the learner is enrolled in, with their progress.
     *
     * Hidden courses are left out unless the current user can see them.
     *
     * @return \stdClass[] Not completed first, then by name: id, fullname, progress (percent of activities, null without
     *     completion tracking), timecompleted, timespent (ever), lastactivity
     */
    public function get_courses(): array {
        global $DB;
        $courses = enrol_get_all_users_courses($this->userid, true, 'enablecompletion');
        if (!$courses) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal(array_keys($courses), SQL_PARAMS_NAMED, 'lmcourse');
        $params['userid'] = $this->userid;
        $times = $DB->get_records_sql(
            "SELECT courseid, timespent, lastactivity
               FROM {local_learnmetrics_course}
              WHERE userid = :userid AND courseid $insql",
            $params
        );
        $completions = $DB->get_records_sql_menu(
            "SELECT course, timecompleted
               FROM {course_completions}
              WHERE userid = :userid AND timecompleted > 0 AND course $insql",
            $params
        );
        $rows = [];
        foreach ($courses as $course) {
            $context = \context_course::instance($course->id);
            if (!$course->visible && !has_capability('moodle/course:viewhiddencourses', $context)) {
                continue;
            }
            $progress = \core_completion\progress::get_course_progress_percentage($course, $this->userid);
            $rows[] = (object)[
                'id' => (int)$course->id,
                'fullname' => format_string($course->fullname, true, ['context' => $context]),
                'progress' => $progress === null ? null : (int)floor($progress),
                'timecompleted' => (int)($completions[$course->id] ?? 0),
                'timespent' => (int)($times[$course->id]->timespent ?? 0),
                'lastactivity' => (int)($times[$course->id]->lastactivity ?? 0),
            ];
        }
        usort($rows, fn($a, $b) => [(bool)$a->timecompleted, \core_text::strtolower($a->fullname), $a->id]
            <=> [(bool)$b->timecompleted, \core_text::strtolower($b->fullname), $b->id]);
        return $rows;
    }
}
