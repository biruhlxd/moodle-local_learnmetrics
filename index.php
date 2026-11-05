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
 * Site dashboard of learning activity.
 *
 * @package    local_learnmetrics
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_learnmetrics\hook\dashboard_output;
use local_learnmetrics\local\charts;
use local_learnmetrics\local\dashboard;
use local_learnmetrics\local\department;

$days = optional_param('days', 30, PARAM_INT);
$categoryid = optional_param('category', 0, PARAM_INT);
$cohortid = optional_param('cohort', 0, PARAM_INT);
$departmentvalue = optional_param('department', '', PARAM_TEXT);
if (!in_array($days, dashboard::PERIODS)) {
    $days = 30;
}

$filters = ['days' => $days, 'category' => $categoryid, 'cohort' => $cohortid, 'department' => $departmentvalue];
admin_externalpage_setup('local_learnmetrics_dashboard', '', $filters);
$PAGE->set_heading(get_string('dashboard', 'local_learnmetrics'));

$dashboard = (new dashboard($days, $categoryid, $cohortid))->set_department($departmentvalue);
$summary = $dashboard->get_summary();

// Filters.
$periods = [];
foreach (dashboard::PERIODS as $period) {
    $periods[] = ['value' => $period, 'name' => get_string('lastndays', 'local_learnmetrics', $period),
        'selected' => $period == $days];
}
$categories = [['value' => 0, 'name' => get_string('allcategories', 'local_learnmetrics'), 'selected' => !$categoryid]];
foreach (core_course_category::make_categories_list('moodle/category:viewcourselist') as $id => $name) {
    $categories[] = ['value' => $id, 'name' => $name, 'selected' => $id == $categoryid];
}
$cohorts = [['value' => 0, 'name' => get_string('allcohorts', 'local_learnmetrics'), 'selected' => !$cohortid]];
foreach ($DB->get_records('cohort', null, 'name', 'id, name, contextid') as $cohort) {
    $cohorts[] = ['value' => $cohort->id, 'selected' => $cohort->id == $cohortid,
        'name' => format_string($cohort->name, true, ['context' => context::instance_by_id($cohort->contextid)])];
}

$departmentfield = department::get_field();
$departments = [];
if ($departmentfield) {
    $departments[] = ['value' => '', 'name' => get_string('alldepartments', 'local_learnmetrics'),
        'selected' => $departmentvalue === ''];
    foreach (department::get_values() as $value) {
        $departments[] = ['value' => $value, 'name' => $value, 'selected' => $value === $departmentvalue];
    }
}

// Charts.
$charts = charts::for_dashboard($dashboard);

// Drill-down: the learners behind each figure, with the same filters.
$learnersurl = function (string $metric, int $courseid = 0) use ($filters): string {
    $params = ['metric' => $metric] + $filters;
    if ($courseid) {
        $params['courseid'] = $courseid;
    }
    return (new moodle_url('/local/learnmetrics/learners.php', $params))->out(false);
};
$topcourses = [];
foreach ($dashboard->get_top_courses() as $topcourse) {
    $topcourses[] = [
        'fullname' => $topcourse->fullname,
        'courseurl' => (new moodle_url('/course/view.php', ['id' => $topcourse->id]))->out(false),
        'hours' => format_float($topcourse->hours, 1),
        'learners' => $topcourse->learners,
        'learnersurl' => $learnersurl(dashboard::METRIC_ACTIVE, $topcourse->id),
    ];
}

// Learners needing attention.
$attention = [];
foreach ($dashboard->get_learners_needing_attention() as $row) {
    $attention[] = [
        'fullname' => $row->fullname,
        'userurl' => (new moodle_url('/user/view.php', ['id' => $row->userid, 'course' => $row->courseid]))->out(false),
        'coursename' => $row->coursename,
        'courseurl' => (new moodle_url('/course/view.php', ['id' => $row->courseid]))->out(false),
        'lastactivity' => $row->lastactivity ? userdate($row->lastactivity, get_string('strftimedatefullshort', 'langconfig'))
            : get_string('never'),
        'timespent' => $row->timespent ? format_time($row->timespent) : '-',
    ];
}

// Sections, links and table footers of other plugins.
$extras = new dashboard_output(dashboard_output::VIEW_SITE, $dashboard, $filters);
\core\di::get(\core\hook\manager::class)->dispatch($extras);

$lastlogid = get_config('local_learnmetrics', 'lastlogid');
$template = [
    'formurl' => (new moodle_url('/local/learnmetrics/index.php'))->out(false),
    'periods' => $periods,
    'categories' => $categories,
    'cohorts' => $cohorts,
    'hasdepartments' => count($departments) > 1,
    'departmentlabel' => $departmentfield->name ?? '',
    'departments' => $departments,
    'cards' => [
        ['label' => get_string('activelearners', 'local_learnmetrics'), 'value' => $summary['activelearners'],
            'url' => $learnersurl(dashboard::METRIC_ACTIVE)],
        ['label' => get_string('learninghours', 'local_learnmetrics'), 'value' => format_float($summary['hours'], 1)],
        ['label' => get_string('hoursperlearner', 'local_learnmetrics'), 'value' => format_float($summary['hoursperlearner'], 1)],
        ['label' => get_string('newenrolments', 'local_learnmetrics'), 'value' => $summary['enrolments'],
            'url' => $learnersurl(dashboard::METRIC_ENROLMENTS)],
        ['label' => get_string('coursecompletions', 'local_learnmetrics'), 'value' => $summary['completions'],
            'url' => $learnersurl(dashboard::METRIC_COMPLETIONS)],
    ],
    'footers' => $extras->get_table_footers(),
    'sections' => $extras->get_sections(),
    'links' => $extras->get_links(),
    'topcourses' => $topcourses,
    'hastopcourses' => !empty($topcourses),
    'charts' => [
        ['chart' => $OUTPUT->render($charts['trend']), 'wide' => true],
        ['chart' => $OUTPUT->render($charts['completion'])],
        ['chart' => $OUTPUT->render($charts['weekday'])],
        ['chart' => isset($charts['topcourses']) ? $OUTPUT->render($charts['topcourses']) : '', 'wide' => true],
    ],
    'attention' => $attention,
    'hasattention' => !empty($attention),
    'inactivedays' => dashboard::INACTIVE_DAYS,
    'notyetprocessed' => $lastlogid === false,
    'reportbuilderurl' => (new moodle_url('/reportbuilder/index.php'))->out(false),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_learnmetrics/dashboard', $template);
echo $OUTPUT->footer();
