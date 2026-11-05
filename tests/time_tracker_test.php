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

namespace local_learnmetrics;

use local_learnmetrics\local\dashboard;
use local_learnmetrics\local\time_tracker;

/**
 * Tests for turning the log into learning time, and the dashboard figures built on it.
 *
 * @package    local_learnmetrics
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(time_tracker::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(dashboard::class)]
final class time_tracker_test extends \advanced_testcase {
    /** @var int A fixed time in the past: 10:00 on a weekday, in the site time zone. */
    private $start;

    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        $this->setTimezone('Europe/London');
        $this->start = (new \DateTime('2026-10-14 10:00:00', \core_date::get_server_timezone_object()))->getTimestamp();
        // Only the log entries written by these tests are processed.
        set_config('lastlogid', (int)$DB->get_field_sql('SELECT MAX(id) FROM {logstore_standard_log}'), 'local_learnmetrics');
    }

    /**
     * Write a log entry, as Moodle does when a user does something.
     *
     * @param int $userid
     * @param int $courseid
     * @param int $time
     * @param array $extra Other fields, such as realuserid or origin.
     */
    private function log(int $userid, int $courseid, int $time, array $extra = []): void {
        global $DB;
        $context = \context_course::instance($courseid);
        $DB->insert_record('logstore_standard_log', (object)($extra + [
            'eventname' => '\core\event\course_viewed',
            'component' => 'core',
            'action' => 'viewed',
            'target' => 'course',
            'crud' => 'r',
            'edulevel' => 2,
            'contextid' => $context->id,
            'contextlevel' => CONTEXT_COURSE,
            'contextinstanceid' => $courseid,
            'userid' => $userid,
            'courseid' => $courseid,
            'anonymous' => 0,
            'timecreated' => $time,
            'origin' => 'web',
        ]));
    }

    /**
     * The stored total of a user in a course.
     *
     * @param int $userid
     * @param int $courseid
     * @return \stdClass|false
     */
    private function total(int $userid, int $courseid) {
        global $DB;
        return $DB->get_record('local_learnmetrics_course', ['userid' => $userid, 'courseid' => $courseid]);
    }

    public function test_sessions_and_gaps(): void {
        $generator = $this->getDataGenerator();
        $course1 = $generator->create_course();
        $course2 = $generator->create_course();
        $user = $generator->create_user();
        $t = $this->start;

        $this->log($user->id, $course1->id, $t);
        $this->log($user->id, $course1->id, $t + 600);            // 10 minutes in course 1.
        $this->log($user->id, $course2->id, $t + 1200);           // 10 more minutes in course 1, then course 2.
        $this->log($user->id, $course1->id, $t + 1200 + 3600);    // An hour later: a new session, nothing counted.
        $this->log($user->id, $course1->id, $t + 1200 + 3900);    // 5 minutes in course 1.

        // Small batches: sessions must carry over from one batch to the next.
        $read = (new time_tracker(30 * MINSECS, 2))->process();
        $this->assertSame(5, $read);

        $total = $this->total($user->id, $course1->id);
        $this->assertEquals(1500, $total->timespent);
        $this->assertEquals(4, $total->events);
        $this->assertEquals(1, $total->activedays);
        $this->assertEquals($t, $total->firstactivity);
        $this->assertEquals($t + 1200 + 3900, $total->lastactivity);
        $this->assertEquals(0, $this->total($user->id, $course2->id)->timespent, 'The last action of a session adds no time');
        $this->assertEquals(1, $this->total($user->id, $course2->id)->events);

        // Nothing new: nothing changes.
        $this->assertSame(0, (new time_tracker())->process());
        $this->assertEquals(1500, $this->total($user->id, $course1->id)->timespent);

        // A later run continues the session from where the last one stopped.
        $this->log($user->id, $course1->id, $t + 1200 + 4500);
        (new time_tracker())->process();
        $this->assertEquals(2100, $this->total($user->id, $course1->id)->timespent);
    }

    /**
     * Store a "still here" ping, as the web service does.
     *
     * @param int $userid
     * @param int $courseid
     * @param int $time
     */
    private function ping(int $userid, int $courseid, int $time): void {
        global $DB;
        $DB->insert_record('local_learnmetrics_ping', (object)['userid' => $userid, 'courseid' => $courseid,
            'timecreated' => $time]);
    }

    public function test_pings_bridge_a_long_page(): void {
        global $DB;
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $user = $generator->create_user();
        $t = $this->start;

        // A SCORM package is launched at 10:00 and stays open for 40 minutes; the next log entry is at 10:41.
        $this->log($user->id, $course->id, $t);
        for ($minute = 1; $minute <= 40; $minute++) {
            $this->ping($user->id, $course->id, $t + $minute * MINSECS);
        }
        $this->log($user->id, $course->id, $t + 41 * MINSECS);
        // A ping too recent to process yet: it waits for the next run.
        $this->ping($user->id, $course->id, $t + 42 * MINSECS);

        $read = (new time_tracker(30 * MINSECS, 20000, $t + 43 * MINSECS))->process();
        $this->assertSame(2, $read);
        $total = $this->total($user->id, $course->id);
        $this->assertEquals(41 * MINSECS, $total->timespent, 'The pings bridge the 41 minutes');
        $this->assertEquals(2, $total->events, 'Pings are not actions');
        $this->assertEquals($t + 41 * MINSECS, $total->lastactivity);
        $this->assertSame(1, $DB->count_records('local_learnmetrics_ping'), 'Processed pings are deleted');

        // Later, the waiting ping is processed: one more minute after the last log entry.
        (new time_tracker(30 * MINSECS, 20000, $t + HOURSECS))->process();
        $this->assertEquals(42 * MINSECS, $this->total($user->id, $course->id)->timespent);
        $this->assertEquals(2, $this->total($user->id, $course->id)->events);
        $this->assertSame(0, $DB->count_records('local_learnmetrics_ping'));
    }

    public function test_without_pings_a_long_page_is_a_gap(): void {
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->log($user->id, $course->id, $this->start);
        $this->log($user->id, $course->id, $this->start + 41 * MINSECS);
        (new time_tracker(30 * MINSECS))->process();
        $this->assertEquals(0, $this->total($user->id, $course->id)->timespent);
    }

    public function test_log_entries_wait_for_the_cutoff(): void {
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $t = $this->start;
        $this->log($user->id, $course->id, $t);
        $this->log($user->id, $course->id, $t + 600);
        $this->ping($user->id, $course->id, $t + 300);

        // At $t + 601, the second entry is too recent; the ping before it is processed.
        $this->assertSame(1, (new time_tracker(null, 20000, $t + 601))->process());
        $this->assertEquals(300, $this->total($user->id, $course->id)->timespent);
        $this->assertSame(1, (new time_tracker(null, 20000, $t + 3600))->process());
        $this->assertEquals(600, $this->total($user->id, $course->id)->timespent);
    }

    public function test_time_is_counted_on_the_day_it_was_spent(): void {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $midnight = (new \DateTime('2026-10-15 00:00:00', \core_date::get_server_timezone_object()))->getTimestamp();

        $this->log($user->id, $course->id, $midnight - 600);
        $this->log($user->id, $course->id, $midnight + 300);
        (new time_tracker())->process();

        $days = $DB->get_records_menu('local_learnmetrics_daily', ['userid' => $user->id], 'day', 'day, timespent');
        $this->assertSame([$midnight - DAYSECS => '900', $midnight => '0'], array_map('strval', $days));
        $this->assertEquals(2, $this->total($user->id, $course->id)->activedays);
    }

    public function test_only_people_learning_are_counted(): void {
        global $CFG;
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $admin = get_admin();
        $t = $this->start;

        $this->log($CFG->siteguest, $course->id, $t);
        $this->log($CFG->siteguest, $course->id, $t + 60);
        $this->log($user->id, $course->id, $t, ['realuserid' => $admin->id]);
        $this->log($user->id, $course->id, $t + 60, ['realuserid' => $admin->id]);
        $this->log($user->id, $course->id, $t + 120, ['origin' => 'cli']);
        $this->log(0, $course->id, $t + 180);
        (new time_tracker())->process();

        $this->assertFalse($this->total($CFG->siteguest, $course->id), 'Guests');
        $this->assertFalse($this->total($user->id, $course->id), '"Log in as" and command line actions');
    }

    public function test_dashboard_figures(): void {
        global $DB;
        $generator = $this->getDataGenerator();
        $category = $generator->create_category();
        $course1 = $generator->create_course(['category' => $category->id]);
        $course2 = $generator->create_course();
        $active = $generator->create_and_enrol($course1, 'student');
        $idle = $generator->create_and_enrol($course1, 'student');
        $other = $generator->create_and_enrol($course2, 'student');
        $cohort = $generator->create_cohort();
        cohort_add_member($cohort->id, $active->id);
        $DB->set_field('user_enrolments', 'timecreated', $this->start - 60 * DAYSECS, []);
        $t = $this->start;

        $this->log($active->id, $course1->id, $t);
        $this->log($active->id, $course1->id, $t + 1800);
        $this->log($other->id, $course2->id, $t);
        $this->log($other->id, $course2->id, $t + 900);
        (new time_tracker())->process();

        $now = $t + 2 * HOURSECS;
        $all = new dashboard(30, 0, 0, $now);
        $summary = $all->get_summary();
        $this->assertSame(2, $summary['activelearners']);
        $this->assertEquals(0.8, $summary['hours']);
        $trend = $all->get_daily_trend();
        $this->assertCount(30, $trend, 'Every day of the period, including quiet ones');
        $this->assertSame(['learners' => 2, 'hours' => 0.8], $trend[(new time_tracker())->day_start($t)]);
        $this->assertSame([$course1->id, $course2->id], array_column($all->get_top_courses(), 'id'));
        $this->assertSame(['completed' => 0, 'inprogress' => 2, 'notstarted' => 1], $all->get_completion_status());
        $this->assertSame([(int)$idle->id], array_map('intval', array_column($all->get_learners_needing_attention(), 'userid')));

        $incategory = new dashboard(30, $category->id, 0, $now);
        $this->assertSame(1, $incategory->get_summary()['activelearners']);
        $this->assertSame([$course1->id], array_column($incategory->get_top_courses(), 'id'));

        $incohort = new dashboard(30, 0, $cohort->id, $now);
        $this->assertSame(1, $incohort->get_summary()['activelearners']);
        $this->assertSame([], $incohort->get_learners_needing_attention(), 'The idle learner is not in the cohort');
    }

    /**
     * Twenty minutes between two actions of three learners in a course: one in a browser with no pings in between
     * (the window was minimised), one in a browser that pinged every minute, and one in the mobile app.
     *
     * @return array [course id, user ids by name]
     */
    private function twenty_minutes(): array {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $users = [];
        foreach (['hidden', 'pinged', 'app'] as $name) {
            $users[$name] = (int)$generator->create_user()->id;
        }
        $t = $this->start;
        $this->log($users['hidden'], $course->id, $t);
        $this->log($users['hidden'], $course->id, $t + 1200);
        $this->log($users['pinged'], $course->id, $t);
        for ($minute = 1; $minute < 20; $minute++) {
            $this->ping($users['pinged'], $course->id, $t + $minute * MINSECS);
        }
        $this->log($users['pinged'], $course->id, $t + 1200);
        $this->log($users['app'], $course->id, $t, ['origin' => 'ws']);
        $this->log($users['app'], $course->id, $t + 1200, ['origin' => 'ws']);
        (new time_tracker(30 * MINSECS, 20000, $t + 2 * HOURSECS))->process();
        return [(int)$course->id, $users];
    }

    public function test_pauses_without_pings_count_only_a_little(): void {
        set_config('pingsince', $this->start - HOURSECS, 'local_learnmetrics');
        [$courseid, $users] = $this->twenty_minutes();
        $this->assertEquals(
            time_tracker::PING_GAP,
            $this->total($users['hidden'], $courseid)->timespent,
            'A browser that sent no pings was hidden or not used'
        );
        $this->assertEquals(1200, $this->total($users['pinged'], $courseid)->timespent, 'Pings show the learner was there');
        $this->assertEquals(1200, $this->total($users['app'], $courseid)->timespent, 'The mobile app does not ping');
        $this->assertEquals(2, $this->total($users['pinged'], $courseid)->events, 'Pings are not actions');
    }

    public function test_pauses_before_pings_started_count_in_full(): void {
        set_config('pingsince', $this->start + DAYSECS, 'local_learnmetrics');
        [$courseid, $users] = $this->twenty_minutes();
        $this->assertEquals(1200, $this->total($users['hidden'], $courseid)->timespent);
    }

    public function test_pauses_count_in_full_when_pings_are_off(): void {
        set_config('pingsince', $this->start - HOURSECS, 'local_learnmetrics');
        set_config('heartbeat', 0, 'local_learnmetrics');
        [$courseid, $users] = $this->twenty_minutes();
        $this->assertEquals(1200, $this->total($users['hidden'], $courseid)->timespent);
    }

    public function test_time_per_activity(): void {
        global $DB;
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $page = $generator->create_module('page', ['course' => $course->id]);
        $package = $generator->create_module('page', ['course' => $course->id]);
        $user = $generator->create_and_enrol($course, 'student');
        $t = $this->start;
        $inactivity = function (\stdClass $module): array {
            return ['contextid' => \context_module::instance($module->cmid)->id, 'contextlevel' => CONTEXT_MODULE,
                'contextinstanceid' => $module->cmid, 'eventname' => '\mod_page\event\course_module_viewed',
                'component' => 'mod_page', 'target' => 'course_module'];
        };

        // 10:00 the course page; 10:02 a page, read for 5 minutes; 10:07 a package, open for 21 minutes with pings;
        // 10:28 back on the course page; 10:30 the course page again.
        $this->log($user->id, $course->id, $t);
        $this->log($user->id, $course->id, $t + 2 * MINSECS, $inactivity($page));
        $this->log($user->id, $course->id, $t + 7 * MINSECS, $inactivity($package));
        for ($minute = 8; $minute <= 27; $minute++) {
            $this->ping($user->id, $course->id, $t + $minute * MINSECS);
        }
        $this->log($user->id, $course->id, $t + 28 * MINSECS);
        $this->log($user->id, $course->id, $t + 30 * MINSECS);
        (new time_tracker(30 * MINSECS, 20000, $t + HOURSECS))->process();

        $times = $DB->get_records_menu('local_learnmetrics_cmtime', ['userid' => $user->id], '', 'cmid, timespent');
        $this->assertEquals([$page->cmid => 5 * MINSECS, $package->cmid => 21 * MINSECS], $times);
        $this->assertEquals(30 * MINSECS, $this->total($user->id, $course->id)->timespent, 'Course time includes both');
        $this->assertEquals(1, $DB->get_field('local_learnmetrics_cmtime', 'events', ['cmid' => $page->cmid]));

        // The course dashboard shows it per activity.
        $this->setAdminUser();
        $dashboard = new \local_learnmetrics\local\course_dashboard(30, $course->id, null, $t + HOURSECS);
        $views = $dashboard->get_activity_views(get_fast_modinfo($course));
        $this->assertEquals([5 * MINSECS, 21 * MINSECS], array_column($views, 'timespent'));

        // Deleting an activity deletes its time.
        course_delete_module($page->cmid);
        $this->assertFalse($DB->record_exists('local_learnmetrics_cmtime', ['cmid' => $page->cmid]));
    }
}
