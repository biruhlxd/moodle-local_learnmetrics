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

namespace local_learnmetrics\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for local_learnmetrics.
 *
 * Learning time and unprocessed pings are stored per user and course (in the course context); the last action of each
 * user, used to join sessions across runs, is stored in the user's context.
 *
 * @package    local_learnmetrics
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describe the data stored.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_learnmetrics_daily', [
            'userid' => 'privacy:metadata:userid',
            'courseid' => 'privacy:metadata:courseid',
            'day' => 'privacy:metadata:day',
            'timespent' => 'privacy:metadata:timespent',
            'events' => 'privacy:metadata:events',
        ], 'privacy:metadata:daily');
        $collection->add_database_table('local_learnmetrics_cmtime', [
            'userid' => 'privacy:metadata:userid',
            'courseid' => 'privacy:metadata:courseid',
            'cmid' => 'privacy:metadata:cmid',
            'day' => 'privacy:metadata:day',
            'timespent' => 'privacy:metadata:timespent',
            'events' => 'privacy:metadata:events',
        ], 'privacy:metadata:cmtime');
        $collection->add_database_table('local_learnmetrics_course', [
            'userid' => 'privacy:metadata:userid',
            'courseid' => 'privacy:metadata:courseid',
            'timespent' => 'privacy:metadata:timespent',
            'events' => 'privacy:metadata:events',
            'activedays' => 'privacy:metadata:activedays',
            'firstactivity' => 'privacy:metadata:firstactivity',
            'lastactivity' => 'privacy:metadata:lastactivity',
        ], 'privacy:metadata:course');
        $collection->add_database_table('local_learnmetrics_session', [
            'userid' => 'privacy:metadata:userid',
            'courseid' => 'privacy:metadata:courseid',
            'timecreated' => 'privacy:metadata:lastaction',
            'web' => 'privacy:metadata:web',
            'cmid' => 'privacy:metadata:cmid',
        ], 'privacy:metadata:session');
        $collection->add_database_table('local_learnmetrics_ping', [
            'userid' => 'privacy:metadata:userid',
            'courseid' => 'privacy:metadata:courseid',
            'timecreated' => 'privacy:metadata:pingtime',
        ], 'privacy:metadata:ping');
        return $collection;
    }

    /**
     * Contexts with data about a user.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $contextlist->add_from_sql(
            "SELECT ctx.id
               FROM {local_learnmetrics_course} lmc
               JOIN {context} ctx ON ctx.instanceid = lmc.courseid AND ctx.contextlevel = :courselevel
              WHERE lmc.userid = :userid",
            ['courselevel' => CONTEXT_COURSE, 'userid' => $userid]
        );
        // Pings not yet processed by the time engine.
        $contextlist->add_from_sql(
            "SELECT ctx.id
               FROM {local_learnmetrics_ping} lmp
               JOIN {context} ctx ON ctx.instanceid = lmp.courseid AND ctx.contextlevel = :courselevel
              WHERE lmp.userid = :userid",
            ['courselevel' => CONTEXT_COURSE, 'userid' => $userid]
        );
        $contextlist->add_from_sql(
            "SELECT ctx.id
               FROM {local_learnmetrics_session} lms
               JOIN {context} ctx ON ctx.instanceid = lms.userid AND ctx.contextlevel = :userlevel
              WHERE lms.userid = :userid",
            ['userlevel' => CONTEXT_USER, 'userid' => $userid]
        );
        return $contextlist;
    }

    /**
     * Users with data in a context.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if ($context instanceof \core\context\course) {
            $userlist->add_from_sql(
                'userid',
                "SELECT userid FROM {local_learnmetrics_course} WHERE courseid = :courseid",
                ['courseid' => $context->instanceid]
            );
            $userlist->add_from_sql(
                'userid',
                "SELECT userid FROM {local_learnmetrics_ping} WHERE courseid = :courseid",
                ['courseid' => $context->instanceid]
            );
        } else if ($context instanceof \core\context\user) {
            $userlist->add_from_sql(
                'userid',
                "SELECT userid FROM {local_learnmetrics_session} WHERE userid = :userid",
                ['userid' => $context->instanceid]
            );
        }
    }

    /**
     * Export a user's learning time.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            $subcontext = [get_string('pluginname', 'local_learnmetrics')];
            if ($context instanceof \core\context\course) {
                $pings = $DB->get_fieldset_sql(
                    'SELECT timecreated FROM {local_learnmetrics_ping}
                      WHERE userid = :userid AND courseid = :courseid ORDER BY timecreated',
                    ['userid' => $userid, 'courseid' => $context->instanceid]
                );
                if ($pings) {
                    writer::with_context($context)->export_data(
                        array_merge($subcontext, [get_string('privacy:pings', 'local_learnmetrics')]),
                        (object)['pings' => array_map(fn($time) => transform::datetime($time), $pings)]
                    );
                }
                $total = $DB->get_record('local_learnmetrics_course', ['userid' => $userid, 'courseid' => $context->instanceid]);
                if (!$total) {
                    continue;
                }
                $days = $DB->get_records(
                    'local_learnmetrics_daily',
                    ['userid' => $userid, 'courseid' => $context->instanceid],
                    'day'
                );
                $activities = $DB->get_records_sql(
                    "SELECT cmid, SUM(timespent) AS timespent, SUM(events) AS events
                       FROM {local_learnmetrics_cmtime}
                      WHERE userid = :userid AND courseid = :courseid
                   GROUP BY cmid
                   ORDER BY cmid",
                    ['userid' => $userid, 'courseid' => $context->instanceid]
                );
                $modinfo = get_fast_modinfo($context->instanceid, -1);
                writer::with_context($context)->export_data($subcontext, (object)[
                    'activities' => array_values(array_map(fn($row) => [
                        'activity' => isset($modinfo->cms[$row->cmid]) ? $modinfo->cms[$row->cmid]->get_formatted_name()
                            : $row->cmid,
                        'timespent' => format_time($row->timespent),
                        'actions' => $row->events,
                    ], $activities)),
                    'timespent' => format_time($total->timespent),
                    'actions' => $total->events,
                    'activedays' => $total->activedays,
                    'firstactivity' => transform::datetime($total->firstactivity),
                    'lastactivity' => transform::datetime($total->lastactivity),
                    'days' => array_values(array_map(fn($day) => [
                        'day' => transform::date($day->day),
                        'timespent' => format_time($day->timespent),
                        'actions' => $day->events,
                    ], $days)),
                ]);
            } else if ($context instanceof \core\context\user && $context->instanceid == $userid) {
                $session = $DB->get_record('local_learnmetrics_session', ['userid' => $userid]);
                if ($session) {
                    writer::with_context($context)->export_data($subcontext, (object)[
                        'lastaction' => transform::datetime($session->timecreated),
                    ]);
                }
            }
        }
    }

    /**
     * Delete everyone's data in a context.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        if ($context instanceof \core\context\course) {
            $DB->delete_records('local_learnmetrics_daily', ['courseid' => $context->instanceid]);
            $DB->delete_records('local_learnmetrics_cmtime', ['courseid' => $context->instanceid]);
            $DB->delete_records('local_learnmetrics_course', ['courseid' => $context->instanceid]);
            $DB->delete_records('local_learnmetrics_ping', ['courseid' => $context->instanceid]);
        } else if ($context instanceof \core\context\user) {
            $DB->delete_records('local_learnmetrics_session', ['userid' => $context->instanceid]);
        }
    }

    /**
     * Delete a user's data in some contexts.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof \core\context\course) {
                $DB->delete_records('local_learnmetrics_daily', ['userid' => $userid, 'courseid' => $context->instanceid]);
                $DB->delete_records('local_learnmetrics_cmtime', ['userid' => $userid, 'courseid' => $context->instanceid]);
                $DB->delete_records('local_learnmetrics_course', ['userid' => $userid, 'courseid' => $context->instanceid]);
                $DB->delete_records('local_learnmetrics_ping', ['userid' => $userid, 'courseid' => $context->instanceid]);
            } else if ($context instanceof \core\context\user && $context->instanceid == $userid) {
                $DB->delete_records('local_learnmetrics_session', ['userid' => $userid]);
            }
        }
    }

    /**
     * Delete some users' data in a context.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;
        $context = $userlist->get_context();
        $userids = $userlist->get_userids();
        if (!$userids) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        if ($context instanceof \core\context\course) {
            $params['courseid'] = $context->instanceid;
            $DB->delete_records_select('local_learnmetrics_daily', "courseid = :courseid AND userid $insql", $params);
            $DB->delete_records_select('local_learnmetrics_cmtime', "courseid = :courseid AND userid $insql", $params);
            $DB->delete_records_select('local_learnmetrics_course', "courseid = :courseid AND userid $insql", $params);
            $DB->delete_records_select('local_learnmetrics_ping', "courseid = :courseid AND userid $insql", $params);
        } else if ($context instanceof \core\context\user) {
            $params['userid'] = $context->instanceid;
            $DB->delete_records_select('local_learnmetrics_session', "userid = :userid AND userid $insql", $params);
        }
    }
}
