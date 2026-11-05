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

namespace local_learnmetrics;

/**
 * Event observers for local_learnmetrics.
 *
 * @package    local_learnmetrics
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    /**
     * Remove the learning time and pings of a deleted user.
     *
     * @param \core\event\user_deleted $event
     */
    public static function user_deleted(\core\event\user_deleted $event): void {
        global $DB;
        $tables = ['local_learnmetrics_daily', 'local_learnmetrics_cmtime', 'local_learnmetrics_course',
            'local_learnmetrics_session', 'local_learnmetrics_ping'];
        foreach ($tables as $table) {
            $DB->delete_records($table, ['userid' => $event->objectid]);
        }
    }

    /**
     * Remove the learning time and unprocessed pings of a deleted course.
     *
     * @param \core\event\course_deleted $event
     */
    public static function course_deleted(\core\event\course_deleted $event): void {
        global $DB;
        $tables = ['local_learnmetrics_daily', 'local_learnmetrics_cmtime', 'local_learnmetrics_course', 'local_learnmetrics_ping'];
        foreach ($tables as $table) {
            $DB->delete_records($table, ['courseid' => $event->objectid]);
        }
    }

    /**
     * Remove the learning time of a deleted activity.
     *
     * @param \core\event\course_module_deleted $event
     */
    public static function course_module_deleted(\core\event\course_module_deleted $event): void {
        global $DB;
        $DB->delete_records('local_learnmetrics_cmtime', ['cmid' => $event->objectid]);
    }
}
