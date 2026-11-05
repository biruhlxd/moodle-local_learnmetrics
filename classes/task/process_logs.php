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

namespace local_learnmetrics\task;

use local_learnmetrics\local\time_tracker;

/**
 * Turns new entries of the standard log into learning time.
 *
 * @package    local_learnmetrics
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class process_logs extends \core\task\scheduled_task {
    /**
     * Task name.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_processlogs', 'local_learnmetrics');
    }

    /**
     * Process the log entries added since the last run.
     */
    public function execute() {
        $enabled = explode(',', (string)get_config('tool_log', 'enabled_stores'));
        if (!in_array('logstore_standard', $enabled)) {
            mtrace('The standard log is not enabled, so learning time cannot be recorded.');
            return;
        }
        $count = (new time_tracker())->process();
        mtrace("Log entries processed: $count");
    }
}
