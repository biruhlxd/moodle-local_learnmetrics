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

use local_learnmetrics\local\course_dashboard;

/**
 * Lets plugins add sections to the course insight page.
 *
 * @package    local_learnmetrics
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\core\attribute\label('Allows plugins to add sections to the course insight page.')]
#[\core\attribute\tags('local_learnmetrics')]
final class insight_output {
    /** @var string[] HTML sections, shown after the activities nobody opened. */
    protected $sections = [];

    /**
     * Constructor.
     *
     * @param course_dashboard $dashboard The dashboard of the course, with the period and the viewer's groups.
     * @param int $days The period, in days.
     */
    public function __construct(
        /** @var course_dashboard The dashboard of the course. */
        protected readonly course_dashboard $dashboard,
        /** @var int The period, in days. */
        protected readonly int $days
    ) {
    }

    /**
     * The dashboard of the course, to count the same learners: see {@see course_dashboard::get_scope_sql()}.
     *
     * @return course_dashboard
     */
    public function get_dashboard(): course_dashboard {
        return $this->dashboard;
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
     * Add a section at the end of the page.
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
