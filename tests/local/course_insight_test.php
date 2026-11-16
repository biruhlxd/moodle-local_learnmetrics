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
 * Tests for the course insight: the completion funnel and the activities nobody opened.
 *
 * @package    local_learnmetrics
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(course_insight::class)]
final class course_insight_test extends \advanced_testcase {
    public function test_funnel(): void {
        global $DB, $CFG;
        require_once($CFG->libdir . '/completionlib.php');
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['enablecompletion' => 1, 'groupmode' => SEPARATEGROUPS, 'groupmodeforce' => 1]);
        $tracked = ['course' => $course->id, 'completion' => COMPLETION_TRACKING_MANUAL];
        $intro = $generator->create_module('page', $tracked + ['name' => 'Intro']);
        $generator->create_module('page', ['course' => $course->id, 'name' => 'Untracked']);
        $lesson = $generator->create_module('page', $tracked + ['name' => 'Lesson']);
        $quiz = $generator->create_module('page', $tracked + ['name' => 'Quiz']);
        $extra = $generator->create_module('page', $tracked + ['name' => 'Extra']);
        $learners = [];
        foreach (['Ann', 'Ben', 'Cas', 'Dee'] as $name) {
            $learners[$name] = $generator->create_and_enrol($course, 'student', ['firstname' => $name]);
        }
        $generator->create_and_enrol($course, 'editingteacher');
        $set = fn($name, $cm, $state = COMPLETION_COMPLETE) => $DB->insert_record('course_modules_completion', (object)[
            'coursemoduleid' => $cm->cmid, 'userid' => $learners[$name]->id, 'completionstate' => $state,
            'timemodified' => time()]);
        // Intro: 3 of 4. Lesson: 3. Quiz: 1, and one failed. Extra: 2, more than the step before.
        foreach (['Ann', 'Ben', 'Cas'] as $name) {
            $set($name, $intro);
            $set($name, $lesson);
        }
        $set('Ann', $quiz, COMPLETION_COMPLETE_PASS);
        $set('Ben', $quiz, COMPLETION_COMPLETE_FAIL);
        $set('Ann', $extra);
        $set('Cas', $extra);
        $this->setAdminUser();

        $funnel = (new course_insight(new course_dashboard(30, $course->id), get_fast_modinfo($course)))->get_funnel();
        $this->assertSame(4, $funnel['learners'], 'Learners only');
        $this->assertSame(['Intro', 'Lesson', 'Quiz', 'Extra'], array_column($funnel['steps'], 'name'), 'Tracked, in order');
        $this->assertSame([3, 3, 1, 2], array_column($funnel['steps'], 'completed'), 'A failed completion does not count');
        $this->assertSame([75, 75, 25, 50], array_column($funnel['steps'], 'percent'));
        $this->assertSame([1, 0, 2, 0], array_column($funnel['steps'], 'drop'), 'The first drop is since enrolment');
        $this->assertSame([false, false, true, false], array_column($funnel['steps'], 'biggest'));

        // A teacher who cannot access all groups sees the learners of their groups.
        $group = $generator->create_group(['courseid' => $course->id]);
        $generator->create_group_member(['groupid' => $group->id, 'userid' => $learners['Dee']->id]);
        $funnel = (new course_insight(new course_dashboard(30, $course->id, [$group->id]), get_fast_modinfo($course)))
            ->get_funnel();
        $this->assertSame(1, $funnel['learners']);
        $this->assertSame([1, 0, 0, 0], array_column($funnel['steps'], 'drop'));
        $this->assertTrue($funnel['steps'][0]->biggest);

        // Without learners or completion tracking, no step is the biggest drop, or there are no steps.
        $funnel = (new course_insight(new course_dashboard(30, $course->id, []), get_fast_modinfo($course)))->get_funnel();
        $this->assertSame(0, $funnel['learners']);
        $this->assertNotContains(true, array_column($funnel['steps'], 'biggest'));
        $DB->set_field('course', 'enablecompletion', 0, ['id' => $course->id]);
        rebuild_course_cache($course->id);
        $funnel = (new course_insight(new course_dashboard(30, $course->id), get_fast_modinfo($course->id)))->get_funnel();
        $this->assertSame([], $funnel['steps']);
    }

    public function test_quiet_activities(): void {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $opened = $generator->create_module('page', ['course' => $course->id, 'name' => 'Opened']);
        $generator->create_module('page', ['course' => $course->id, 'name' => 'Never opened']);
        $timed = $generator->create_module('page', ['course' => $course->id, 'name' => 'Time only']);
        $generator->create_module('label', ['course' => $course->id, 'name' => 'A text area']);
        $learner = $generator->create_and_enrol($course, 'student');
        $DB->insert_record('logstore_standard_log', (object)[
            'eventname' => '\mod_page\event\course_module_viewed',
            'component' => 'mod_page',
            'action' => 'viewed',
            'target' => 'course_module',
            'crud' => 'r',
            'edulevel' => 2,
            'contextid' => \context_module::instance($opened->cmid)->id,
            'contextlevel' => CONTEXT_MODULE,
            'contextinstanceid' => $opened->cmid,
            'userid' => $learner->id,
            'courseid' => $course->id,
            'anonymous' => 0,
            'timecreated' => time() - HOURSECS,
            'origin' => 'web',
        ]);
        $DB->insert_record('local_learnmetrics_cmtime', (object)['userid' => $learner->id, 'courseid' => $course->id,
            'cmid' => $timed->cmid, 'day' => time() - HOURSECS, 'timespent' => 120, 'events' => 2]);
        $this->setAdminUser();

        $insight = new course_insight(new course_dashboard(30, $course->id), get_fast_modinfo($course));
        $this->assertSame(['Never opened'], array_column($insight->get_quiet_activities(), 'name'));
    }
}
