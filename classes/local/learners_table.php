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

use core_table\sql_table;
use html_writer;
use moodle_url;
use stdClass;

/**
 * The learners behind a dashboard figure: paginated, sortable and downloadable.
 *
 * @package    local_learnmetrics
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class learners_table extends sql_table {
    /**
     * Constructor.
     *
     * @param dashboard $dashboard The dashboard, with its filters.
     * @param string $metric One of {@see dashboard::METRICS}.
     * @param moodle_url $baseurl The page, with its parameters.
     * @param string $download Data format to download in, empty to show the table.
     */
    public function __construct(dashboard $dashboard, string $metric, moodle_url $baseurl, string $download = '') {
        parent::__construct('local_learnmetrics_learners_' . $metric);
        $this->is_downloading(
            $download,
            clean_filename(get_string('learners', 'local_learnmetrics') . '-' . $metric),
            get_string('learners', 'local_learnmetrics')
        );
        $this->define_baseurl($baseurl);
        $this->define_columns(['fullname', 'coursename', 'timespent', 'lastactivity', 'timecompleted']);
        $this->define_headers([
            get_string('learner', 'local_learnmetrics'),
            get_string('course'),
            get_string($this->is_downloading() ? 'hoursinperiod' : 'timespentinperiod', 'local_learnmetrics'),
            get_string('lastactivity', 'local_learnmetrics'),
            get_string('completionstatus', 'local_learnmetrics'),
        ]);
        $this->sortable(true, 'timespent', SORT_DESC);
        $this->useridfield = 'userid';
        $this->set_attribute('class', 'generaltable local-learnmetrics-learners');

        [$sql, $params] = $dashboard->get_learners_sql($metric);
        $this->set_sql('lm.*', "($sql) lm", '1 = 1', $params);
    }

    /**
     * Learner name, linked to the profile.
     *
     * @param stdClass $row
     * @return string
     */
    public function col_fullname($row) {
        $name = fullname($row, has_capability('moodle/site:viewfullnames', $this->get_context()));
        if ($this->is_downloading()) {
            return $name;
        }
        $params = ['id' => $row->userid];
        if ($row->courseid != SITEID) {
            $params['course'] = $row->courseid;
        }
        return html_writer::link(new moodle_url('/user/view.php', $params), $name);
    }

    /**
     * Course name, linked to the course; time outside courses is recorded on the site course.
     *
     * @param stdClass $row
     * @return string
     */
    public function col_coursename(stdClass $row): string {
        if ($row->courseid == SITEID) {
            return get_string('outsidecourses', 'local_learnmetrics');
        }
        $name = format_string($row->coursename, true, ['context' => \context_course::instance($row->courseid)]);
        if ($this->is_downloading()) {
            return $name;
        }
        return html_writer::link(new moodle_url('/course/view.php', ['id' => $row->courseid]), $name);
    }

    /**
     * Time spent in the period.
     *
     * @param stdClass $row
     * @return string
     */
    public function col_timespent(stdClass $row): string {
        if ($this->is_downloading()) {
            return format_float($row->timespent / HOURSECS, 2);
        }
        return $row->timespent ? format_time((int)$row->timespent) : '-';
    }

    /**
     * Last activity in the course.
     *
     * @param stdClass $row
     * @return string
     */
    public function col_lastactivity(stdClass $row): string {
        return $row->lastactivity ? userdate($row->lastactivity, get_string('strftimedatetimeshort', 'langconfig'))
            : get_string('never');
    }

    /**
     * Course completion.
     *
     * @param stdClass $row
     * @return string
     */
    public function col_timecompleted(stdClass $row): string {
        if ($row->courseid == SITEID) {
            return '-';
        }
        if ($row->timecompleted) {
            return get_string(
                'completedon',
                'local_learnmetrics',
                userdate($row->timecompleted, get_string('strftimedatefullshort', 'langconfig'))
            );
        }
        return get_string('notcompleted', 'local_learnmetrics');
    }
}
