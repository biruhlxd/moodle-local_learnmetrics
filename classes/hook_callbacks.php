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

use core\hook\output\before_footer_html_generation;

/**
 * Hook callbacks of local_learnmetrics.
 *
 * @package    local_learnmetrics
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {
    /**
     * Start the "still here" pings on pages of a course (the course page and its activities, including the SCORM
     * player), for logged-in users who are not guests, when the setting is on.
     *
     * @param before_footer_html_generation $hook
     */
    public static function before_footer_html_generation(before_footer_html_generation $hook): void {
        global $PAGE;
        $courseid = self::get_ping_courseid($PAGE);
        if ($courseid) {
            $PAGE->requires->js_call_amd('local_learnmetrics/heartbeat', 'init', [
                $courseid,
                $PAGE->pagetype === 'mod-scorm-player',
            ]);
        }
    }

    /**
     * The course for which a page sends pings.
     *
     * @param \moodle_page $page
     * @return int The course id, or 0 for no pings.
     */
    public static function get_ping_courseid(\moodle_page $page): int {
        if (during_initial_install() || (defined('AJAX_SCRIPT') && AJAX_SCRIPT)) {
            return 0;
        }
        if (get_config('local_learnmetrics', 'heartbeat') === '0') {
            return 0;
        }
        if (!isloggedin() || isguestuser() || \core\session\manager::is_loggedinas()) {
            return 0;
        }
        $coursecontext = $page->context->get_course_context(false);
        if (!$coursecontext || $coursecontext->instanceid == SITEID) {
            return 0;
        }
        return (int)$coursecontext->instanceid;
    }
}
