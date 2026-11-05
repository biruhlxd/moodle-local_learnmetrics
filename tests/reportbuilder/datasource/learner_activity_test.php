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

namespace local_learnmetrics\reportbuilder\datasource;

use core_reportbuilder\local\filters\duration;
use core_reportbuilder\manager;
use core_reportbuilder\tests\core_reportbuilder_testcase;

/**
 * Tests for the Learner activity report source.
 *
 * @package    local_learnmetrics
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(learner_activity::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_learnmetrics\reportbuilder\local\entities\learning_time::class)]
final class learner_activity_test extends core_reportbuilder_testcase {
    public function test_learning_time_columns_and_filter(): void {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['fullname' => 'Course 1']);
        $busy = $generator->create_and_enrol($course, 'student', ['firstname' => 'Busy', 'lastname' => 'Learner']);
        $generator->create_and_enrol($course, 'student', ['firstname' => 'New', 'lastname' => 'Learner']);
        $DB->insert_record('local_learnmetrics_course', (object)['userid' => $busy->id, 'courseid' => $course->id,
            'timespent' => 5400, 'events' => 12, 'activedays' => 3, 'firstactivity' => 1000, 'lastactivity' => 2000]);

        /** @var \core_reportbuilder_generator $rbgenerator */
        $rbgenerator = $this->getDataGenerator()->get_plugin_generator('core_reportbuilder');
        $report = $rbgenerator->create_report(['name' => 'Activity', 'source' => learner_activity::class, 'default' => 0]);
        $columns = ['user:firstname', 'learning_time:timespent', 'learning_time:hours', 'learning_time:events',
            'learning_time:activedays'];
        foreach ($columns as $i => $column) {
            $rbgenerator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => $column,
                'sortenabled' => $i == 0]);
        }

        $content = $this->get_custom_report_content($report->get('id'));
        $this->assertEquals([
            ['Busy', format_time(5400), format_float(1.5, 1), 12, 3],
            ['New', '-', format_float(0, 1), 0, 0],
        ], array_map('array_values', $content));

        // Filter: at least an hour of learning time.
        $rbgenerator->create_filter(['reportid' => $report->get('id'), 'uniqueidentifier' => 'learning_time:timespent']);
        $content = $this->get_custom_report_content($report->get('id'), 0, [
            'learning_time:timespent_operator' => duration::DURATION_MINIMUM,
            'learning_time:timespent_value' => 1,
            'learning_time:timespent_unit' => HOURSECS,
        ]);
        $this->assertSame(['Busy'], array_column(array_map('array_values', $content), 0));
    }

    public function test_report_with_default_setup(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $report = $this->getDataGenerator()->get_plugin_generator('core_reportbuilder')->create_report(
            ['name' => 'Default', 'source' => learner_activity::class]
        );
        $this->assertNotNull(manager::get_report_from_persistent($report));
    }
}
