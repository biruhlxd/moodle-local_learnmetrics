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
 * Tests for the progress of one learner: learning time, courses and who can see it.
 *
 * @package    local_learnmetrics
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(my_progress::class)]
final class my_progress_test extends \advanced_testcase {
    /** @var \stdClass[] Courses: safety (completion tracking, one activity), done (completed by ann), hidden. */
    private $courses = [];

    /** @var \stdClass[] Users: ann and ben (learners), mia (manager). */
    private $users = [];

    /** @var int The end of the period of the tests. */
    private $now;

    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        $this->now = time() + MINSECS;
        $generator = $this->getDataGenerator();
        $this->courses['safety'] = $generator->create_course(['fullname' => 'Safety', 'enablecompletion' => 1]);
        $generator->create_module('page', ['course' => $this->courses['safety']->id, 'completion' => COMPLETION_TRACKING_MANUAL]);
        $this->courses['done'] = $generator->create_course(['fullname' => 'Done', 'enablecompletion' => 1]);
        $this->courses['hidden'] = $generator->create_course(['fullname' => 'Hidden', 'visible' => 0]);
        $this->users['ann'] = $generator->create_user(['firstname' => 'Ann']);
        $this->users['ben'] = $generator->create_user(['firstname' => 'Ben']);
        $this->users['mia'] = $generator->create_user(['firstname' => 'Mia']);
        foreach ($this->courses as $course) {
            $generator->enrol_user($this->users['ann']->id, $course->id, 'student');
        }
        $generator->enrol_user($this->users['ben']->id, $this->courses['safety']->id, 'student');
        $generator->role_assign($DB->get_field('role', 'id', ['shortname' => 'manager']), $this->users['mia']->id);
        $DB->insert_record('course_completions', (object)['userid' => $this->users['ann']->id,
            'course' => $this->courses['done']->id, 'timeenrolled' => 0, 'timestarted' => 0,
            'timecompleted' => $this->now - DAYSECS, 'reaggregate' => 0]);

        // Ann: half an hour in Safety and half an hour outside courses today, an hour 40 days ago.
        $tracker = new time_tracker();
        $today = $tracker->day_start($this->now);
        $longago = $tracker->day_start($this->now - 40 * DAYSECS);
        $rows = [
            [$this->courses['safety']->id, $today, 1800],
            [SITEID, $today, 1800],
            [$this->courses['done']->id, $longago, 3600],
        ];
        foreach ($rows as [$courseid, $day, $seconds]) {
            $DB->insert_record('local_learnmetrics_daily', (object)['userid' => $this->users['ann']->id,
                'courseid' => $courseid, 'day' => $day, 'timespent' => $seconds, 'events' => 3]);
        }
        $DB->insert_record('local_learnmetrics_course', (object)['userid' => $this->users['ann']->id,
            'courseid' => $this->courses['safety']->id, 'timespent' => 1800, 'events' => 3, 'activedays' => 1,
            'firstactivity' => $this->now - HOURSECS, 'lastactivity' => $this->now - HOURSECS]);
    }

    public function test_summary_and_daily_hours(): void {
        $progress = new my_progress($this->users['ann']->id, 30, $this->now);
        $this->assertSame(['hours' => 1.0, 'activedays' => 1, 'totalhours' => 2.0], $progress->get_summary());

        $hours = $progress->get_daily_hours();
        $this->assertCount(30, $hours);
        $this->assertEquals(1.0, end($hours));
        $this->assertEquals(1.0, array_sum($hours));

        $ben = new my_progress($this->users['ben']->id, 30, $this->now);
        $this->assertSame(['hours' => 0.0, 'activedays' => 0, 'totalhours' => 0.0], $ben->get_summary());
    }

    public function test_courses(): void {
        $this->setUser($this->users['ann']);
        $courses = (new my_progress($this->users['ann']->id, 30, $this->now))->get_courses();
        // Not completed first; the hidden course is left out for the learner.
        $this->assertSame(['Safety', 'Done'], array_column($courses, 'fullname'));
        [$safety, $done] = $courses;
        $this->assertSame(0, $safety->progress);
        $this->assertSame(0, $safety->timecompleted);
        $this->assertSame(1800, $safety->timespent);
        $this->assertSame($this->now - HOURSECS, $safety->lastactivity);
        $this->assertSame($this->now - DAYSECS, $done->timecompleted);
        $this->assertSame(0, $done->timespent);

        // An administrator sees the hidden course too.
        $this->setAdminUser();
        $courses = (new my_progress($this->users['ann']->id, 30, $this->now))->get_courses();
        $this->assertSame(['Hidden', 'Safety', 'Done'], array_column($courses, 'fullname'));
        $this->assertNull($courses[0]->progress);
    }

    public function test_who_can_see_progress(): void {
        $ann = $this->users['ann']->id;

        $this->setUser($this->users['ann']);
        $this->assertTrue(my_progress::can_view($ann));
        $this->assertFalse(my_progress::can_view($this->users['ben']->id));

        $this->setUser($this->users['mia']);
        $this->assertTrue(my_progress::can_view($ann));

        $this->setGuestUser();
        $this->assertFalse(my_progress::can_view($ann));
        $this->assertFalse(my_progress::can_view((int)guest_user()->id));

        // A parent: a role with the capability, given in the child's user context.
        $parentroleid = create_role('Parent', 'parent', '');
        set_role_contextlevels($parentroleid, [CONTEXT_USER]);
        assign_capability('local/learnmetrics:viewuserprogress', CAP_ALLOW, $parentroleid, \context_system::instance());
        role_assign($parentroleid, $this->users['ben']->id, \context_user::instance($ann));
        $this->setUser($this->users['ben']);
        $this->assertTrue(my_progress::can_view($ann));
        $this->assertFalse(my_progress::can_view($this->users['mia']->id));
    }

    public function test_profile_link(): void {
        global $CFG;
        require_once($CFG->dirroot . '/local/learnmetrics/lib.php');
        $this->setUser($this->users['ann']);
        $tree = new \core_user\output\myprofile\tree();
        local_learnmetrics_myprofile_navigation($tree, $this->users['ann'], true, null);
        $this->assertArrayHasKey('local_learnmetrics_progress', $tree->nodes);

        // Not on another learner's profile.
        $tree = new \core_user\output\myprofile\tree();
        local_learnmetrics_myprofile_navigation($tree, $this->users['ben'], false, null);
        $this->assertArrayNotHasKey('local_learnmetrics_progress', $tree->nodes);
    }
}
