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
 * The progress of a learner: learning time, courses and required training.
 *
 * @package    local_learnmetrics
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_learnmetrics\hook\my_progress_output;
use local_learnmetrics\local\charts;
use local_learnmetrics\local\dashboard;
use local_learnmetrics\local\my_progress;

$userid = optional_param('userid', 0, PARAM_INT);
$days = optional_param('days', 30, PARAM_INT);
if (!in_array($days, dashboard::PERIODS)) {
    $days = 30;
}

require_login(null, false);
$userid = $userid ?: (int)$USER->id;
$user = core_user::get_user($userid, '*', MUST_EXIST);
if ($user->deleted || !my_progress::can_view($userid)) {
    throw new required_capability_exception(
        context_user::instance($userid, IGNORE_MISSING) ?: context_system::instance(),
        $userid == $USER->id ? 'local/learnmetrics:viewownprogress' : 'local/learnmetrics:viewuserprogress',
        'nopermissions',
        ''
    );
}
$iscurrentuser = $userid == $USER->id;
$title = $iscurrentuser ? get_string('myprogress', 'local_learnmetrics')
    : get_string('progressof', 'local_learnmetrics', fullname($user));

$url = new moodle_url('/local/learnmetrics/my.php', ['days' => $days] + ($iscurrentuser ? [] : ['userid' => $userid]));
$PAGE->set_url($url);
$PAGE->set_context(context_user::instance($userid));
$PAGE->set_pagelayout('report');
$PAGE->set_title($title);
$PAGE->set_heading(fullname($user));
$PAGE->navigation->extend_for_user($user);
$PAGE->navbar->add($title, $url);

$progress = new my_progress($userid, $days);
$summary = $progress->get_summary();

$periods = [];
foreach (dashboard::PERIODS as $period) {
    $periods[] = ['value' => $period, 'name' => get_string('lastndays', 'local_learnmetrics', $period),
        'selected' => $period == $days];
}

$dateformat = get_string('strftimedatefullshort', 'langconfig');
$date = fn(int $time) => $time ? userdate($time, $dateformat) : '-';

$courses = [];
$completed = 0;
foreach ($progress->get_courses() as $course) {
    $completed += $course->timecompleted ? 1 : 0;
    $courses[] = [
        'fullname' => $course->fullname,
        'url' => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
        'hasprogress' => $course->progress !== null,
        'progress' => $course->progress,
        'completed' => $course->timecompleted ? $date($course->timecompleted) : null,
        'timespent' => $course->timespent ? format_time($course->timespent) : '-',
        'lastactivity' => $course->lastactivity ? $date($course->lastactivity) : get_string('never'),
    ];
}

// Sections of other plugins.
$extras = new my_progress_output($userid, $days);
\core\di::get(\core\hook\manager::class)->dispatch($extras);

$template = [
    'formurl' => (new moodle_url('/local/learnmetrics/my.php'))->out(false),
    'userid' => $iscurrentuser ? null : $userid,
    'periods' => $periods,
    'cards' => [
        ['label' => get_string('learninghours', 'local_learnmetrics'), 'value' => format_float($summary['hours'], 1)],
        ['label' => get_string('activedays', 'local_learnmetrics'), 'value' => $summary['activedays']],
        ['label' => get_string('totallearninghours', 'local_learnmetrics'), 'value' => format_float($summary['totalhours'], 1)],
        ['label' => get_string('coursescompleted', 'local_learnmetrics'),
            'value' => get_string('nofm', 'local_learnmetrics', ['n' => $completed, 'm' => count($courses)])],
    ],
    'chart' => $OUTPUT->render(charts::daily_hours($progress->get_daily_hours())),
    'courses' => $courses,
    'hascourses' => !empty($courses),
    'sections' => $extras->get_sections(),
];

echo $OUTPUT->header();
echo $OUTPUT->heading($title);
echo $OUTPUT->render_from_template('local_learnmetrics/my_progress', $template);
echo $OUTPUT->footer();
