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

/**
 * Tests for the activity completion grid.
 *
 * @package    local_learnmetrics
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(completion_grid::class)]
final class completion_grid_test extends \advanced_testcase {
    public function test_grid(): void {
        global $DB, $CFG;
        require_once($CFG->libdir . '/completionlib.php');
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['enablecompletion' => 1, 'groupmode' => SEPARATEGROUPS, 'groupmodeforce' => 1]);
        $intro = $generator->create_module('page', ['course' => $course->id, 'name' => 'Intro',
            'completion' => COMPLETION_TRACKING_MANUAL]);
        $quiz = $generator->create_module('page', ['course' => $course->id, 'name' => 'Check',
            'completion' => COMPLETION_TRACKING_MANUAL]);
        $generator->create_module('page', ['course' => $course->id, 'name' => 'Untracked']);
        $ann = $generator->create_and_enrol($course, 'student', ['firstname' => 'Ann', 'lastname' => 'A']);
        $ben = $generator->create_and_enrol($course, 'student', ['firstname' => 'Ben', 'lastname' => 'B']);
        $generator->create_and_enrol($course, 'editingteacher');
        $set = fn($user, $cm, $state) => $DB->insert_record('course_modules_completion', (object)['coursemoduleid' => $cm->cmid,
            'userid' => $user->id, 'completionstate' => $state, 'timemodified' => time()]);
        $set($ann, $intro, COMPLETION_COMPLETE);
        $set($ann, $quiz, COMPLETION_COMPLETE_PASS);
        $set($ben, $intro, COMPLETION_COMPLETE_FAIL);
        $this->setAdminUser();

        $grid = (new completion_grid(new course_dashboard(30, $course->id), get_fast_modinfo($course)))->get_grid();
        $this->assertSame(['Intro', 'Check'], array_column($grid['activities'], 'name'), 'Tracked activities, in order');
        $this->assertSame([50, 50], array_column($grid['activities'], 'percent'));
        $this->assertSame(['Ann A', 'Ben B'], array_column($grid['learners'], 'fullname'), 'Learners only, by name');
        $this->assertSame([100, 0], array_column($grid['learners'], 'percent'));
        $this->assertSame(
            [completion_grid::STATE_FAIL, completion_grid::STATE_NONE],
            $grid['learners'][1]->states
        );

        // A teacher who cannot access all groups sees the learners of their groups.
        $group = $generator->create_group(['courseid' => $course->id]);
        $generator->create_group_member(['groupid' => $group->id, 'userid' => $ben->id]);
        $grid = (new completion_grid(new course_dashboard(30, $course->id, [$group->id]), get_fast_modinfo($course)))->get_grid();
        $this->assertSame(['Ben B'], array_column($grid['learners'], 'fullname'));
        $this->assertSame([0, 0], array_column($grid['activities'], 'percent'));

        // Without completion tracking, nothing.
        $DB->set_field('course', 'enablecompletion', 0, ['id' => $course->id]);
        rebuild_course_cache($course->id);
        $grid = (new completion_grid(new course_dashboard(30, $course->id), get_fast_modinfo($course->id)))->get_grid();
        $this->assertSame([], $grid['activities']);
    }
}
