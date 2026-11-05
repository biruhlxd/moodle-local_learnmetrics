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

use local_learnmetrics\local\dashboard;

/**
 * Lets plugins add to the site or a course learning dashboard: sections, links, and HTML under its tables.
 *
 * @package    local_learnmetrics
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\core\attribute\label('Allows plugins to add sections, links and table footers to the learning dashboards.')]
#[\core\attribute\tags('local_learnmetrics')]
final class dashboard_output {
    /** @var string The site dashboard. */
    const VIEW_SITE = 'site';

    /** @var string The dashboard of a course. */
    const VIEW_COURSE = 'course';

    /** @var string[] Tables that can get a footer; courses is on the site dashboard, activities and scorm on a course's. */
    const TABLES = ['trend', 'courses', 'attention', 'activities', 'scorm'];

    /** @var string[] HTML under each table, by table. */
    protected $footers = [];

    /** @var string[] HTML sections, shown after the learners needing attention. */
    protected $sections = [];

    /** @var array[] Links at the end of the page: url, text. */
    protected $links = [];

    /**
     * Constructor.
     *
     * @param string $view {@see VIEW_SITE} or {@see VIEW_COURSE}.
     * @param dashboard $dashboard The dashboard, with its filters.
     * @param array $params The filters as URL parameters: days, and category, cohort, department (site) or courseid
     *     (course).
     */
    public function __construct(
        /** @var string The dashboard shown. */
        protected readonly string $view,
        /** @var dashboard The dashboard, with its filters. */
        protected readonly dashboard $dashboard,
        /** @var array The filters as URL parameters. */
        protected readonly array $params
    ) {
    }

    /**
     * The dashboard shown.
     *
     * @return string {@see VIEW_SITE} or {@see VIEW_COURSE}.
     */
    public function get_view(): string {
        return $this->view;
    }

    /**
     * The dashboard, with its filters.
     *
     * @return dashboard A {@see \local_learnmetrics\local\course_dashboard} for a course.
     */
    public function get_dashboard(): dashboard {
        return $this->dashboard;
    }

    /**
     * The filters as URL parameters.
     *
     * @return array
     */
    public function get_params(): array {
        return $this->params;
    }

    /**
     * Add HTML under a table, such as a download form.
     *
     * @param string $table One of {@see TABLES}.
     * @param string $html
     */
    public function add_table_footer(string $table, string $html): void {
        $this->footers[$table] = ($this->footers[$table] ?? '') . $html;
    }

    /**
     * Add a section after the learners needing attention.
     *
     * @param string $html
     */
    public function add_section(string $html): void {
        $this->sections[] = $html;
    }

    /**
     * Add a link at the end of the page.
     *
     * @param \moodle_url $url
     * @param string $text
     */
    public function add_link(\moodle_url $url, string $text): void {
        $this->links[] = ['url' => $url->out(false), 'text' => $text];
    }

    /**
     * HTML under each table.
     *
     * @return string[] By table; tables without a footer are left out.
     */
    public function get_table_footers(): array {
        return $this->footers;
    }

    /**
     * The added sections.
     *
     * @return string[]
     */
    public function get_sections(): array {
        return $this->sections;
    }

    /**
     * The added links.
     *
     * @return array[] url, text
     */
    public function get_links(): array {
        return $this->links;
    }
}
