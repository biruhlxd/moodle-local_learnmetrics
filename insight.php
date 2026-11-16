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
 * Course insight: the completion funnel of a course and the activities nobody opened, for its teachers.
 *
 * @package    local_learnmetrics
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_learnmetrics\hook\insight_output;
use local_learnmetrics\local\course_dashboard;
use local_learnmetrics\local\course_insight;
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

$url = new moodle_url('/local/learnmetrics/insight.php', ['id' => $course->id, 'days' => $days]);
$title = get_string('courseinsight', 'local_learnmetrics');
$backurl = new moodle_url('/local/learnmetrics/course.php', ['id' => $course->id, 'days' => $days]);
$PAGE->set_url($url);
$PAGE->set_pagelayout('report');
$PAGE->set_title($title . moodle_page::TITLE_SEPARATOR . format_string($course->shortname, true, ['context' => $context]));
$PAGE->set_heading(format_string($course->fullname, true, ['context' => $context]));
$PAGE->set_secondary_active_tab('coursereports');
$PAGE->navbar->add(get_string('coursedashboard', 'local_learnmetrics'), $backurl);
$PAGE->navbar->add($title, $url);

$groupids = course_dashboard::get_group_limit($course, $USER->id);
$dashboard = new course_dashboard($days, $course->id, $groupids);
$insight = new course_insight($dashboard, get_fast_modinfo($course));
$funnel = $insight->get_funnel();

$periods = [];
foreach (dashboard::PERIODS as $period) {
    $periods[] = ['value' => $period, 'name' => get_string('lastndays', 'local_learnmetrics', $period),
        'selected' => $period == $days];
}

$quiet = [];
foreach ($insight->get_quiet_activities() as $activity) {
    $quiet[] = [
        'name' => $activity->name,
        'url' => $activity->url->out(false),
        'iconurl' => $activity->iconurl->out(false),
        'modfullname' => $activity->modfullname,
    ];
}

// Sections of other plugins.
$extras = new insight_output($dashboard, $days);
\core\di::get(\core\hook\manager::class)->dispatch($extras);

echo $OUTPUT->header();
echo $OUTPUT->heading($title);
echo $OUTPUT->render_from_template('local_learnmetrics/course_insight', [
    'formurl' => (new moodle_url('/local/learnmetrics/insight.php'))->out(false),
    'courseid' => $course->id,
    'periods' => $periods,
    'groupsonly' => $groupids !== null,
    'completionenabled' => (bool)$course->enablecompletion,
    'haslearners' => $funnel['learners'] > 0,
    'learnercount' => $funnel['learners'],
    'hassteps' => !empty($funnel['steps']),
    'steps' => array_map(fn($step) => [
        'name' => $step->name,
        'url' => $step->url ? $step->url->out(false) : '',
        'completed' => $step->completed,
        'percent' => $step->percent,
        'drop' => $step->drop,
        'biggest' => $step->biggest,
    ], $funnel['steps']),
    'quiet' => $quiet,
    'hasquiet' => !empty($quiet),
    'days' => $days,
    'sections' => $extras->get_sections(),
    'backurl' => $backurl->out(false),
]);
echo $OUTPUT->footer();
