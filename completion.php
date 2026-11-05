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
 * Activity completion of a course: the learners by the activities with completion tracking.
 *
 * @package    local_learnmetrics
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_learnmetrics\local\completion_grid;
use local_learnmetrics\local\course_dashboard;

$courseid = required_param('id', PARAM_INT);
$page = optional_param('page', 0, PARAM_INT);
$dataformat = optional_param('download', '', PARAM_ALPHA);
$perpage = 50;

$course = get_course($courseid);
if ($course->id == SITEID) {
    throw new moodle_exception('invalidcourseid');
}
require_login($course);
$context = context_course::instance($course->id);
require_capability('local/learnmetrics:viewcoursedashboard', $context);

$dashboard = new course_dashboard(30, $course->id, course_dashboard::get_group_limit($course, $USER->id));
$grid = (new completion_grid($dashboard, get_fast_modinfo($course)))->get_grid();
$labels = [
    completion_grid::STATE_NONE => get_string('completion_none', 'local_learnmetrics'),
    completion_grid::STATE_COMPLETE => get_string('completion_complete', 'local_learnmetrics'),
    completion_grid::STATE_FAIL => get_string('completion_fail', 'local_learnmetrics'),
];

if ($dataformat) {
    $columns = array_merge(
        [get_string('learner', 'local_learnmetrics'), get_string('progresspercent', 'local_learnmetrics')],
        array_column($grid['activities'], 'name')
    );
    $rows = [];
    foreach ($grid['learners'] as $learner) {
        $states = array_map(fn($state) => $labels[$state], $learner->states);
        $rows[] = array_merge([$learner->fullname, $learner->percent], $states);
    }
    \core\session\manager::write_close();
    $filename = clean_filename(get_string('activitycompletion', 'local_learnmetrics') . '-'
        . format_string($course->shortname, true, ['context' => $context]) . '-' . date('Y-m-d'));
    \core\dataformat::download_data($filename, $dataformat, $columns, $rows);
    die;
}

$url = new moodle_url('/local/learnmetrics/completion.php', ['id' => $course->id]);
$title = get_string('activitycompletion', 'local_learnmetrics');
$PAGE->set_url($url, ['page' => $page]);
$PAGE->set_pagelayout('report');
$PAGE->set_title($title . moodle_page::TITLE_SEPARATOR . format_string($course->shortname, true, ['context' => $context]));
$PAGE->set_heading(format_string($course->fullname, true, ['context' => $context]));
$PAGE->set_secondary_active_tab('coursereports');
$backurl = new moodle_url('/local/learnmetrics/course.php', ['id' => $course->id]);
$PAGE->navbar->add(get_string('coursedashboard', 'local_learnmetrics'), $backurl);
$PAGE->navbar->add($title, $url);

$total = count($grid['learners']);
$learners = array_slice($grid['learners'], $page * $perpage, $perpage);
$classes = [
    completion_grid::STATE_NONE => '',
    completion_grid::STATE_COMPLETE => 'text-success',
    completion_grid::STATE_FAIL => 'text-danger',
];
$symbols = [completion_grid::STATE_NONE => '–', completion_grid::STATE_COMPLETE => '✓', completion_grid::STATE_FAIL => '✗'];

echo $OUTPUT->header();
echo $OUTPUT->heading($title);
echo $OUTPUT->render_from_template('local_learnmetrics/completion_grid', [
    'hasactivities' => !empty($grid['activities']),
    'haslearners' => $total > 0,
    'completionenabled' => (bool)$course->enablecompletion,
    'activities' => $grid['activities'],
    'learners' => array_map(fn($learner) => [
        'fullname' => $learner->fullname,
        'userurl' => (new moodle_url('/user/view.php', ['id' => $learner->id, 'course' => $course->id]))->out(false),
        'percent' => $learner->percent,
        'cells' => array_map(fn($state) => [
            'symbol' => $symbols[$state],
            'label' => $labels[$state],
            'class' => $classes[$state],
        ], $learner->states),
    ], $learners),
    'learnercount' => $total,
    'groupsonly' => course_dashboard::get_group_limit($course, $USER->id) !== null,
    'paging' => $OUTPUT->render(new paging_bar($total, $page, $perpage, $url)),
    'download' => $total ? $OUTPUT->download_dataformat_selector(
        get_string('downloadas', 'table'),
        $url,
        'download',
        ['id' => $course->id]
    ) : '',
    'backurl' => $backurl->out(false),
]);
echo $OUTPUT->footer();
