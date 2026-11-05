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
 * Tests for the drill-down from the dashboard figures to the learners behind them.
 *
 * @package    local_learnmetrics
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(dashboard::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(learners_table::class)]
final class learners_test extends \advanced_testcase {
    /** @var int The end of the period of the tests. */
    private $now;

    /** @var \stdClass[] Courses: incategory, other. */
    private $courses = [];

    /** @var \stdClass[] Users: ada, bob, cy. */
    private $users = [];

    /** @var \stdClass A cohort with Ada. */
    private $cohort;

    /** @var \stdClass The category of the first course. */
    private $category;

    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        // A little ahead, so the enrolments made below are in the period.
        $this->now = time() + MINSECS;
        $generator = $this->getDataGenerator();
        $this->category = $generator->create_category();
        $this->courses['incategory'] = $generator->create_course(['category' => $this->category->id,
            'fullname' => 'In category']);
        $this->courses['other'] = $generator->create_course(['fullname' => 'Other']);
        foreach (['Ada', 'Bob', 'Cy'] as $name) {
            $this->users[strtolower($name)] = $generator->create_user(['firstname' => $name, 'lastname' => 'Learner']);
        }
        $this->cohort = $generator->create_cohort();
        cohort_add_member($this->cohort->id, $this->users['ada']->id);

        // Ada learns in both courses, Bob in the first; Cy was enrolled long ago and completed the other course.
        $generator->enrol_user($this->users['ada']->id, $this->courses['incategory']->id, 'student');
        $generator->enrol_user($this->users['ada']->id, $this->courses['other']->id, 'student');
        $generator->enrol_user($this->users['bob']->id, $this->courses['incategory']->id, 'student');
        $generator->enrol_user($this->users['cy']->id, $this->courses['other']->id, 'student');
        $DB->set_field(
            'user_enrolments',
            'timecreated',
            $this->now - 100 * DAYSECS,
            ['userid' => $this->users['cy']->id]
        );
        $DB->insert_record('course_completions', (object)['userid' => $this->users['cy']->id,
            'course' => $this->courses['other']->id, 'timeenrolled' => 0, 'timestarted' => 0,
            'timecompleted' => $this->now - DAYSECS, 'reaggregate' => 0]);

        $today = (new time_tracker())->day_start($this->now);
        $this->add_time('ada', 'incategory', $today, 1800);
        $this->add_time('ada', 'incategory', $today - DAYSECS, 1800);
        $this->add_time('ada', 'other', $today, 600);
        $this->add_time('bob', 'incategory', $today, 900);
        // Long before the period: not counted.
        $this->add_time('bob', 'other', $today - 60 * DAYSECS, 900);
    }

    /**
     * Record learning time, as the time tracker does.
     *
     * @param string $user
     * @param string $course
     * @param int $day
     * @param int $seconds
     */
    private function add_time(string $user, string $course, int $day, int $seconds): void {
        global $DB;
        $userid = $this->users[$user]->id;
        $courseid = $this->courses[$course]->id;
        $DB->insert_record('local_learnmetrics_daily', (object)['userid' => $userid, 'courseid' => $courseid,
            'day' => $day, 'timespent' => $seconds, 'events' => 1]);
        $total = $DB->get_record('local_learnmetrics_course', ['userid' => $userid, 'courseid' => $courseid]);
        if ($total) {
            $total->timespent += $seconds;
            $total->lastactivity = max($total->lastactivity, $day);
            $DB->update_record('local_learnmetrics_course', $total);
        } else {
            $DB->insert_record('local_learnmetrics_course', (object)['userid' => $userid, 'courseid' => $courseid,
                'timespent' => $seconds, 'events' => 1, 'activedays' => 1, 'firstactivity' => $day, 'lastactivity' => $day]);
        }
    }

    /**
     * Learner and course of each row.
     *
     * @param \stdClass[] $rows
     * @return string[]
     */
    private function names(array $rows): array {
        $names = array_map(fn($row) => $row->firstname . ' / ' . $row->coursename, $rows);
        sort($names);
        return $names;
    }

    public function test_lists_match_the_figures(): void {
        $dashboard = new dashboard(30, 0, 0, $this->now);
        $summary = $dashboard->get_summary();

        $active = $dashboard->get_learners(dashboard::METRIC_ACTIVE);
        $this->assertSame(['Ada / In category', 'Ada / Other', 'Bob / In category'], $this->names($active));
        $this->assertCount($summary['activelearners'], array_unique(array_column($active, 'userid')));
        $this->assertEquals($summary['hours'], round(array_sum(array_column($active, 'timespent')) / HOURSECS, 1));
        $ada = array_values(array_filter($active, fn($row) => $row->courseid == $this->courses['incategory']->id
            && $row->userid == $this->users['ada']->id))[0];
        $this->assertEquals(3600, $ada->timespent, 'Time spent in the period, over both days');

        $enrolments = $dashboard->get_learners(dashboard::METRIC_ENROLMENTS);
        $this->assertCount($summary['enrolments'], $enrolments);
        $this->assertSame(['Ada / In category', 'Ada / Other', 'Bob / In category'], $this->names($enrolments));

        $completions = $dashboard->get_learners(dashboard::METRIC_COMPLETIONS);
        $this->assertCount($summary['completions'], $completions);
        $this->assertSame(['Cy / Other'], $this->names($completions));
        $this->assertEquals($this->now - DAYSECS, $completions[0]->timecompleted);
    }

    public function test_lists_follow_the_filters(): void {
        $incategory = new dashboard(30, $this->category->id, 0, $this->now);
        $this->assertSame(
            ['Ada / In category', 'Bob / In category'],
            $this->names($incategory->get_learners(dashboard::METRIC_ACTIVE))
        );
        $this->assertSame([], $incategory->get_learners(dashboard::METRIC_COMPLETIONS));

        $incohort = new dashboard(30, 0, $this->cohort->id, $this->now);
        $this->assertSame(
            ['Ada / In category', 'Ada / Other'],
            $this->names($incohort->get_learners(dashboard::METRIC_ACTIVE))
        );

        // A course from the list of courses with the most learning time.
        $course = (new dashboard(30, 0, 0, $this->now))->set_course($this->courses['other']->id);
        $top = (new dashboard(30, 0, 0, $this->now))->get_top_courses();
        $other = array_values(array_filter($top, fn($row) => $row->id == $this->courses['other']->id))[0];
        $this->assertCount($other->learners, $course->get_learners(dashboard::METRIC_ACTIVE));
        $this->assertSame(['Ada / Other'], $this->names($course->get_learners(dashboard::METRIC_ACTIVE)));
        $this->assertSame(1, $course->get_summary()['activelearners']);
    }

    public function test_unknown_metric(): void {
        $this->expectException(\coding_exception::class);
        (new dashboard(30))->get_learners_sql('nonsense');
    }

    public function test_table(): void {
        $this->setAdminUser();
        $dashboard = new dashboard(30, 0, 0, $this->now);
        $table = new learners_table(
            $dashboard,
            dashboard::METRIC_ACTIVE,
            new \moodle_url('/local/learnmetrics/learners.php')
        );
        ob_start();
        $table->out(2, false);
        $html = ob_get_clean();
        $this->assertSame(3, $table->totalrows);
        // Sorted by time spent, most first, two per page.
        $this->assertStringContainsString('Ada Learner', $html);
        $this->assertStringContainsString(format_time(3600), $html);
        $this->assertStringContainsString(format_time(900), $html);
        $this->assertStringNotContainsString(format_time(600), $html, 'Ada in the other course is on the next page');
        $this->assertStringContainsString(get_string('notcompleted', 'local_learnmetrics'), $html);
    }

    public function test_download_values(): void {
        $this->setAdminUser();
        // The download starts as soon as the table knows its format.
        $this->expectOutputRegex('/.*/');
        $dashboard = new dashboard(30, 0, 0, $this->now);
        $table = new learners_table(
            $dashboard,
            dashboard::METRIC_COMPLETIONS,
            new \moodle_url('/local/learnmetrics/learners.php'),
            'csv'
        );
        $this->assertTrue((bool)$table->is_downloading());
        // Only the values matter here: setting up the table would start sending the file.
        $rows = array_map(fn($row) => $table->format_row($row), $dashboard->get_learners(dashboard::METRIC_COMPLETIONS));
        $this->assertCount(1, $rows);
        $this->assertSame('Cy Learner', $rows[0]['fullname'], 'Plain text, no link');
        $this->assertSame('Other', $rows[0]['coursename']);
        $this->assertSame(format_float(0, 2), $rows[0]['timespent'], 'Hours, as a number');
    }
}
