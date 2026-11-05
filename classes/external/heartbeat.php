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

namespace local_learnmetrics\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Record that a user is still on a page of a course: a "still here" ping.
 *
 * The time engine treats pings like log entries when it measures sessions, so time spent on one page (a SCORM
 * package, a long text or video) counts even though Moodle logs nothing while it is open.
 *
 * @package    local_learnmetrics
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class heartbeat extends external_api {
    /** @var int At most one ping per user is stored in this many seconds. */
    const MIN_INTERVAL = 50;

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'The course of the page the user is on'),
        ]);
    }

    /**
     * Record a ping.
     *
     * @param int $courseid
     * @return array recorded: whether the ping was stored (not when pings are off, too frequent, or for a guest
     *     or "log in as" session)
     */
    public static function execute(int $courseid): array {
        global $DB, $USER;
        ['courseid' => $courseid] = self::validate_parameters(self::execute_parameters(), ['courseid' => $courseid]);
        $course = get_course($courseid);
        $context = \context_course::instance($course->id);
        // Checks that the user may access the course: enrolled, or allowed to view it.
        self::validate_context($context);
        if (!is_enrolled($context, $USER, '', true) && !is_viewing($context, $USER)) {
            throw new \required_capability_exception($context, 'moodle/course:view', 'nopermissions', '');
        }

        if (
            $course->id == SITEID || isguestuser() || \core\session\manager::is_loggedinas()
                || get_config('local_learnmetrics', 'heartbeat') === '0'
        ) {
            return ['recorded' => false];
        }
        $now = time();
        $last = $DB->get_field_sql(
            'SELECT MAX(timecreated) FROM {local_learnmetrics_ping} WHERE userid = :userid',
            ['userid' => $USER->id]
        );
        if ($last && $now - $last < self::MIN_INTERVAL) {
            return ['recorded' => false];
        }
        $DB->insert_record('local_learnmetrics_ping', (object)['userid' => $USER->id, 'courseid' => $course->id,
            'timecreated' => $now]);
        return ['recorded' => true];
    }

    /**
     * Returned value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'recorded' => new external_value(PARAM_BOOL, 'Whether the ping was stored'),
        ]);
    }
}
