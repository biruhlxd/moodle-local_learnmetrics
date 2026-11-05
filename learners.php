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

/**
 * The learners behind a figure of the site or course dashboard, with the dashboard's filters.
 *
 * From the site dashboard (local/learnmetrics:viewdashboard) it keeps the period, category and cohort; from a
 * course dashboard (view=course, local/learnmetrics:viewcoursedashboard) it keeps the period and the user's groups.
 *
 * @package    local_learnmetrics
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_learnmetrics\local\course_dashboard;
use local_learnmetrics\local\dashboard;
use local_learnmetrics\local\learners_table;

$metric = optional_param('metric', dashboard::METRIC_ACTIVE, PARAM_ALPHA);
$days = optional_param('days', 30, PARAM_INT);
$categoryid = optional_param('category', 0, PARAM_INT);
$cohortid = optional_param('cohort', 0, PARAM_INT);
$departmentvalue = optional_param('department', '', PARAM_TEXT);
$courseid = optional_param('courseid', 0, PARAM_INT);
$view = optional_param('view', '', PARAM_ALPHA);
$download = optional_param('download', '', PARAM_ALPHA);
if (!in_array($metric, dashboard::METRICS)) {
    $metric = dashboard::METRIC_ACTIVE;
}
if (!in_array($days, dashboard::PERIODS)) {
    $days = 30;
}
$course = $courseid ? get_course($courseid) : null;
$coursemode = $course && $course->id != SITEID
    && ($view === 'course' || !has_capability('local/learnmetrics:viewdashboard', context_system::instance()));

if ($coursemode) {
    // From a course dashboard: teachers of the course, limited to their groups in separate groups mode.
    $params = ['metric' => $metric, 'days' => $days, 'courseid' => $course->id, 'view' => 'course'];
    $url = new moodle_url('/local/learnmetrics/learners.php', $params);
    require_login($course);
    $context = context_course::instance($course->id);
    require_capability('local/learnmetrics:viewcoursedashboard', $context);
    $PAGE->set_url($url);
    $PAGE->set_pagelayout('report');
    $PAGE->set_secondary_active_tab('coursereports');
    $PAGE->set_heading(format_string($course->fullname, true, ['context' => $context]));
    $backurl = new moodle_url('/local/learnmetrics/course.php', ['id' => $course->id, 'days' => $days]);
    $PAGE->navbar->add(get_string('coursedashboard', 'local_learnmetrics'), $backurl);
    $dashboard = new course_dashboard($days, $course->id, course_dashboard::get_group_limit($course, $USER->id));
} else {
    // From the site dashboard.
    $filters = ['days' => $days, 'category' => $categoryid, 'cohort' => $cohortid, 'department' => $departmentvalue];
    $params = ['metric' => $metric] + $filters + ($courseid ? ['courseid' => $courseid] : []);
    $url = new moodle_url('/local/learnmetrics/learners.php', $params);
    admin_externalpage_setup('local_learnmetrics_dashboard', '', $params, $url, ['pagelayout' => 'report']);
    $PAGE->set_heading(get_string('dashboard', 'local_learnmetrics'));
    $backurl = new moodle_url('/local/learnmetrics/index.php', $filters);
    $dashboard = (new dashboard($days, $categoryid, $cohortid))->set_course($courseid)->set_department($departmentvalue);
}
$table = new learners_table($dashboard, $metric, $url, $download);

$labels = [
    dashboard::METRIC_ACTIVE => get_string('activelearners', 'local_learnmetrics'),
    dashboard::METRIC_ENROLMENTS => get_string('newenrolments', 'local_learnmetrics'),
    dashboard::METRIC_COMPLETIONS => get_string('coursecompletions', 'local_learnmetrics'),
];
$title = $labels[$metric];
if ($course && !$coursemode) {
    $title .= ': ' . format_string($course->fullname, true, ['context' => context_course::instance($course->id)]);
}

if (!$table->is_downloading()) {
    $PAGE->set_title($title);
    $PAGE->navbar->add($title);
    echo $OUTPUT->header();
    echo $OUTPUT->heading($title);
    echo html_writer::tag('p', get_string('lastndays', 'local_learnmetrics', $days), ['class' => 'text-muted']);
    echo html_writer::link($backurl, get_string('backtodashboard', 'local_learnmetrics'), ['class' => 'd-block mb-3']);
}
$table->out(50, false);
if (!$table->is_downloading()) {
    echo $OUTPUT->footer();
}
