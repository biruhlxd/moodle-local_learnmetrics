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
 * Learning dashboard of one course, for its teachers.
 *
 * @package    local_learnmetrics
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_learnmetrics\hook\dashboard_output;
use local_learnmetrics\local\charts;
use local_learnmetrics\local\course_dashboard;
use local_learnmetrics\local\dashboard;

$courseid = required_param('id', PARAM_INT);
$days = optional_param('days', 30, PARAM_INT);
if (!in_array($days, dashboard::PERIODS)) {
    $days = 30;
}

$course = get_course($courseid);
if ($course->id == SITEID) {
    throw new moodle_exception('invalidcourseid');
}
require_login($course);
$context = context_course::instance($course->id);
require_capability('local/learnmetrics:viewcoursedashboard', $context);

$url = new moodle_url('/local/learnmetrics/course.php', ['id' => $course->id, 'days' => $days]);
$PAGE->set_url($url);
$PAGE->set_pagelayout('report');
$PAGE->set_title(get_string('coursedashboard', 'local_learnmetrics') . moodle_page::TITLE_SEPARATOR
    . format_string($course->shortname, true, ['context' => $context]));
$PAGE->set_heading(format_string($course->fullname, true, ['context' => $context]));
$PAGE->set_secondary_active_tab('coursereports');
$PAGE->navbar->add(get_string('coursedashboard', 'local_learnmetrics'), $url);

$groupids = course_dashboard::get_group_limit($course, $USER->id);
$dashboard = new course_dashboard($days, $course->id, $groupids);
$summary = $dashboard->get_summary();
$charts = charts::for_dashboard($dashboard, false);

$periods = [];
foreach (dashboard::PERIODS as $period) {
    $periods[] = ['value' => $period, 'name' => get_string('lastndays', 'local_learnmetrics', $period),
        'selected' => $period == $days];
}

// Drill-down: the learners behind each figure.
$learnersurl = function (string $metric) use ($days, $course): string {
    return (new moodle_url(
        '/local/learnmetrics/learners.php',
        ['metric' => $metric, 'days' => $days, 'courseid' => $course->id, 'view' => 'course']
    ))->out(false);
};

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

$activities = [];
foreach ($dashboard->get_activity_views(get_fast_modinfo($course)) as $activity) {
    $activities[] = [
        'name' => $activity->name,
        'url' => $activity->url ? $activity->url->out(false) : null,
        'iconurl' => $activity->iconurl->out(false),
        'modfullname' => $activity->modfullname,
        'views' => $activity->views,
        'learners' => $activity->learners,
        'timespent' => $activity->timespent ? format_time($activity->timespent) : '-',
    ];
}

$packages = [];
foreach ($dashboard->get_scorm_time(get_fast_modinfo($course)) as $package) {
    $packages[] = [
        'name' => $package->name,
        'url' => $package->url ? $package->url->out(false) : null,
        'learners' => $package->learners,
        'attempts' => $package->attempts,
        'time' => $package->seconds ? format_time($package->seconds) : '-',
        'timeperlearner' => $package->secondsperlearner ? format_time($package->secondsperlearner) : '-',
        'noreport' => $package->noreport,
    ];
}

// Sections, links and table footers of other plugins.
$extras = new dashboard_output(dashboard_output::VIEW_COURSE, $dashboard, ['courseid' => $course->id, 'days' => $days]);
\core\di::get(\core\hook\manager::class)->dispatch($extras);

$template = [
    'formurl' => (new moodle_url('/local/learnmetrics/course.php'))->out(false),
    'courseid' => $course->id,
    'periods' => $periods,
    'nogroups' => $groupids === [],
    'groupsonly' => !empty($groupids),
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
    'charts' => [
        ['chart' => $OUTPUT->render($charts['trend']), 'wide' => true],
        ['chart' => $OUTPUT->render($charts['completion'])],
        ['chart' => $OUTPUT->render($charts['weekday'])],
    ],
    'footers' => $extras->get_table_footers(),
    'sections' => $extras->get_sections(),
    'links' => $extras->get_links(),
    'attention' => $attention,
    'hasattention' => !empty($attention),
    'inactivedays' => dashboard::INACTIVE_DAYS,
    'activities' => $activities,
    'completionurl' => (new moodle_url('/local/learnmetrics/completion.php', ['id' => $course->id]))->out(false),
    'hasactivities' => !empty($activities),
    'packages' => $packages,
    'haspackages' => !empty($packages),
];

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('coursedashboard', 'local_learnmetrics'));
echo $OUTPUT->render_from_template('local_learnmetrics/course_dashboard', $template);
echo $OUTPUT->footer();
