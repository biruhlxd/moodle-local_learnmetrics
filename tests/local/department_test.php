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
 * Tests for the department filter: the field, its values, and the dashboards filtered by it.
 *
 * @package    local_learnmetrics
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(department::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(dashboard::class)]
final class department_test extends \advanced_testcase {
    /** @var \stdClass The course. */
    private $course;

    /** @var \stdClass[] Learners: ann (Sales, region North), ben (Sales), cat (Finance, region South), dan (no department). */
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
        $region = $generator->create_custom_profile_field(['datatype' => 'menu', 'shortname' => 'region', 'name' => 'Region',
            'param1' => "North\nSouth"]);
        $people = [
            'ann' => ['Sales', 'North'],
            'ben' => ['Sales', ''],
            'cat' => ['Finance', 'South'],
            'dan' => ['', ''],
        ];
        $day = (new time_tracker())->day_start($this->now);
        foreach ($people as $name => [$department, $regionvalue]) {
            $user = $generator->create_and_enrol($this->course, 'student', ['firstname' => ucfirst($name),
                'department' => $department]);
            if ($regionvalue !== '') {
                $DB->insert_record('user_info_data', (object)['userid' => $user->id, 'fieldid' => $region->id,
                    'data' => $regionvalue, 'dataformat' => 0]);
            }
            $DB->insert_record('local_learnmetrics_daily', (object)['userid' => $user->id, 'courseid' => $this->course->id,
                'day' => $day, 'timespent' => 3600, 'events' => 3]);
            $this->users[$name] = $user;
        }
        // A suspended user's department is not offered.
        $generator->create_user(['department' => 'Former', 'suspended' => 1]);
    }

    public function test_field_and_values(): void {
        // The core Department field by default.
        $field = department::get_field();
        $this->assertSame('department', $field->userfield);
        $this->assertSame(['Finance', 'Sales'], department::get_values());

        // A custom profile field.
        set_config('departmentfield', 'profile_field_region', 'local_learnmetrics');
        $field = department::get_field();
        $this->assertSame('Region', $field->name);
        $this->assertSame('', $field->userfield);
        $this->assertSame(['North', 'South'], department::get_values());
        $this->assertArrayHasKey('profile_field_region', department::get_field_options());

        // Off, or a custom field that no longer exists.
        set_config('departmentfield', '', 'local_learnmetrics');
        $this->assertNull(department::get_field());
        $this->assertSame([], department::get_values());
        set_config('departmentfield', 'profile_field_gone', 'local_learnmetrics');
        $this->assertNull(department::get_field());
    }

    public function test_dashboard_by_department(): void {
        $summary = fn(string $department) => (new dashboard(30, 0, 0, $this->now))->set_department($department)->get_summary();
        $this->assertSame(4, $summary('')['activelearners']);
        $this->assertSame(2, $summary('Sales')['activelearners']);
        $this->assertEquals(2.0, $summary('Sales')['hours']);
        $this->assertSame(1, $summary('Finance')['activelearners']);
        $this->assertSame(0, $summary('Nobody')['activelearners']);

        set_config('departmentfield', 'profile_field_region', 'local_learnmetrics');
        $this->assertSame(1, $summary('North')['activelearners']);
        $this->assertSame(0, $summary('Sales')['activelearners']);

        $learners = (new dashboard(30, 0, 0, $this->now))->set_department('South')->get_learners(dashboard::METRIC_ACTIVE);
        $this->assertSame([(int)$this->users['cat']->id], array_map('intval', array_column($learners, 'userid')));
    }
}
