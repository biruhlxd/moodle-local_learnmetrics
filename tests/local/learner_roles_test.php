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
 * Tests that the dashboards count only learners: the learner roles and site administrators settings.
 *
 * @package    local_learnmetrics
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(dashboard::class)]
final class learner_roles_test extends \advanced_testcase {
    /** @var \stdClass */
    private $course;

    /** @var \stdClass[] Users: student, teacher, admin (also enrolled as a student), outsider (no role anywhere). */
    private $users = [];

    /** @var int The end of the period of the tests. */
    private $now;

    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        $this->now = time() + MINSECS;
        $generator = $this->getDataGenerator();
        $this->course = $generator->create_course();
        $this->users['student'] = $generator->create_and_enrol($this->course, 'student', ['firstname' => 'Student']);
        $this->users['teacher'] = $generator->create_and_enrol($this->course, 'editingteacher', ['firstname' => 'Teacher']);
        $this->users['admin'] = get_admin();
        $generator->enrol_user($this->users['admin']->id, $this->course->id, 'student');
        $this->users['outsider'] = $generator->create_user(['firstname' => 'Outsider']);

        // Everyone has learning time in the course, and outside courses.
        $day = (new time_tracker())->day_start($this->now);
        foreach ($this->users as $name => $user) {
            foreach ([$this->course->id, SITEID] as $courseid) {
                $DB->insert_record('local_learnmetrics_daily', (object)['userid' => $user->id, 'courseid' => $courseid,
                    'day' => $day, 'timespent' => 1800, 'events' => 2]);
                $DB->insert_record('local_learnmetrics_course', (object)['userid' => $user->id, 'courseid' => $courseid,
                    'timespent' => 1800, 'events' => 2, 'activedays' => 1, 'firstactivity' => $day,
                    'lastactivity' => $day - 30 * DAYSECS]);
            }
        }
        $DB->set_field('user_enrolments', 'timecreated', $this->now - 20 * DAYSECS, []);
    }

    /**
     * First names of the active learners of a dashboard, and of the learners behind other figures.
     *
     * @param dashboard $dashboard
     * @return array
     */
    private function figures(dashboard $dashboard): array {
        $names = function (array $rows): array {
            $names = array_values(array_unique(array_column($rows, 'firstname')));
            sort($names);
            return $names;
        };
        return [
            'activelearners' => $dashboard->get_summary()['activelearners'],
            'hours' => $dashboard->get_summary()['hours'],
            'active' => $names($dashboard->get_learners(dashboard::METRIC_ACTIVE)),
            'enrolments' => $names($dashboard->get_learners(dashboard::METRIC_ENROLMENTS)),
            'attention' => $names(array_map(
                fn($row) => (object)['firstname' => explode(' ', $row->fullname)[0]],
                $dashboard->get_learners_needing_attention()
            )),
            'top' => array_column($dashboard->get_top_courses(), 'learners'),
            'trend' => array_sum(array_column($dashboard->get_daily_trend(), 'learners')),
            'status' => array_sum($dashboard->get_completion_status()),
        ];
    }

    public function test_only_students_by_default(): void {
        $figures = $this->figures(new dashboard(30, 0, 0, $this->now));
        $this->assertSame(1, $figures['activelearners'], 'Not the teacher, the admin or the user without a role');
        $this->assertEquals(1.0, $figures['hours'], 'The student in the course and outside courses');
        $this->assertSame(['Student'], $figures['active']);
        $this->assertSame(['Student'], $figures['enrolments']);
        $this->assertSame(['Student'], $figures['attention']);
        $this->assertEquals([1], $figures['top']);
        $this->assertSame(1, $figures['trend']);
        $this->assertSame(1, $figures['status']);

        $course = new course_dashboard(30, $this->course->id, null, $this->now);
        $this->assertSame(1, $course->get_summary()['activelearners']);
        $this->assertEquals(0.5, $course->get_summary()['hours']);
    }

    public function test_include_admins(): void {
        set_config('includeadmins', 1, 'local_learnmetrics');
        $figures = $this->figures(new dashboard(30, 0, 0, $this->now));
        $this->assertSame(2, $figures['activelearners'], 'The admin is enrolled as a student');
        $this->assertSame(['Admin', 'Student'], $figures['active']);
    }

    public function test_other_learner_roles(): void {
        global $DB;
        $editingteacher = $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
        set_config('learnerroles', $editingteacher, 'local_learnmetrics');
        $this->assertSame(['Teacher'], $this->figures(new dashboard(30, 0, 0, $this->now))['active']);

        // No role chosen: everyone but the admin.
        set_config('learnerroles', '', 'local_learnmetrics');
        $figures = $this->figures(new dashboard(30, 0, 0, $this->now));
        $this->assertSame(3, $figures['activelearners']);
        $this->assertSame(['Outsider', 'Student', 'Teacher'], $figures['active']);
    }

    public function test_default_roles(): void {
        unset_config('learnerroles', 'local_learnmetrics');
        $this->assertSame(array_map('intval', array_keys(get_archetype_roles('student'))), dashboard::get_learner_roleids());
    }
}
