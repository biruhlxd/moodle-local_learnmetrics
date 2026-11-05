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
 * Callbacks of local_learnmetrics.
 *
 * @package    local_learnmetrics
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Add the course dashboard to the course's Reports menu.
 *
 * @param navigation_node $navigation The course administration node.
 * @param stdClass $course
 * @param context $context The course context.
 */
function local_learnmetrics_extend_navigation_course(navigation_node $navigation, stdClass $course, context $context): void {
    if ($course->id == SITEID || !has_capability('local/learnmetrics:viewcoursedashboard', $context)) {
        return;
    }
    $url = new moodle_url('/local/learnmetrics/course.php', ['id' => $course->id]);
    $name = get_string('coursedashboard', 'local_learnmetrics');
    $icon = new pix_icon('i/report', '');
    // The Reports node is removed when it has no reports of its own: then the dashboard goes in the course menu.
    $reports = $navigation->get('coursereports');
    if ($reports) {
        $reports->add($name, $url, navigation_node::TYPE_SETTING, null, 'local_learnmetrics_course', $icon);
    } else {
        $navigation->add($name, $url, navigation_node::TYPE_SETTING, null, 'local_learnmetrics_course', $icon);
    }
}

/**
 * Add the learner's progress page to the Reports section of the profile.
 *
 * @param \core_user\output\myprofile\tree $tree
 * @param stdClass $user The user whose profile it is.
 * @param bool $iscurrentuser
 * @param stdClass|null $course The course of the profile, null for the site profile.
 */
function local_learnmetrics_myprofile_navigation(
    \core_user\output\myprofile\tree $tree,
    stdClass $user,
    bool $iscurrentuser,
    ?stdClass $course
): void {
    if (isguestuser($user)) {
        return;
    }
    if (!\local_learnmetrics\local\my_progress::can_view($user->id)) {
        return;
    }
    $url = new moodle_url('/local/learnmetrics/my.php', $iscurrentuser ? [] : ['userid' => $user->id]);
    $name = get_string($iscurrentuser ? 'myprogress' : 'userprogress', 'local_learnmetrics');
    $tree->add_node(new \core_user\output\myprofile\node('reports', 'local_learnmetrics_progress', $name, null, $url));
}
