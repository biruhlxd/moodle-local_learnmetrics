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

namespace local_learnmetrics\hook;

/**
 * Lets plugins add sections to the learning progress page of a user.
 *
 * @package    local_learnmetrics
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\core\attribute\label('Allows plugins to add sections to the learning progress page of a user.')]
#[\core\attribute\tags('local_learnmetrics')]
final class my_progress_output {
    /** @var string[] HTML sections, shown before the courses. */
    protected $sections = [];

    /**
     * Constructor.
     *
     * @param int $userid The user whose progress is shown; the viewer may be someone else.
     * @param int $days The period, in days.
     */
    public function __construct(
        /** @var int The user whose progress is shown. */
        protected readonly int $userid,
        /** @var int The period, in days. */
        protected readonly int $days
    ) {
    }

    /**
     * The user whose progress is shown.
     *
     * @return int
     */
    public function get_userid(): int {
        return $this->userid;
    }

    /**
     * The period, in days.
     *
     * @return int
     */
    public function get_days(): int {
        return $this->days;
    }

    /**
     * Add a section after the chart, before the courses.
     *
     * @param string $html
     */
    public function add_section(string $html): void {
        $this->sections[] = $html;
    }

    /**
     * The added sections.
     *
     * @return string[]
     */
    public function get_sections(): array {
        return $this->sections;
    }
}
