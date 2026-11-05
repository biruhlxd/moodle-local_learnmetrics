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

use core_reportbuilder\local\filters\select;
use core_reportbuilder\manager;
use core_reportbuilder\tests\core_reportbuilder_testcase;
use local_learnmetrics\reportbuilder\local\entities\scorm_attempt;

/**
 * Tests for the SCORM attempts report source.
 *
 * @package    local_learnmetrics
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(scorm_attempts::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(scorm_attempt::class)]
final class scorm_attempts_test extends core_reportbuilder_testcase {
    /**
     * Store an attempt with its tracks, as mod_scorm does.
     *
     * @param \stdClass $scorm
     * @param int $userid
     * @param int $number Attempt number.
     * @param array $tracks Element => value.
     * @param int $time Time the values were stored.
     */
    private function attempt(\stdClass $scorm, int $userid, int $number, array $tracks, int $time): void {
        global $DB;
        $scoid = $DB->get_field_select(
            'scorm_scoes',
            'id',
            "scorm = :scorm AND scormtype = 'sco'",
            ['scorm' => $scorm->id],
            MUST_EXIST
        );
        $attemptid = $DB->insert_record('scorm_attempt', (object)['userid' => $userid, 'scormid' => $scorm->id,
            'attempt' => $number]);
        foreach ($tracks as $element => $value) {
            $elementid = $DB->get_field('scorm_element', 'id', ['element' => $element])
                ?: $DB->insert_record('scorm_element', (object)['element' => $element]);
            $DB->insert_record('scorm_scoes_value', (object)['scoid' => $scoid, 'attemptid' => $attemptid,
                'elementid' => $elementid, 'value' => $value, 'timemodified' => $time++]);
        }
    }

    public function test_attempts(): void {
        $this->resetAfterTest();
        // The SCORM generator uploads the package as the current user.
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['fullname' => 'Safety']);
        $scorm = $generator->create_module('scorm', ['course' => $course->id, 'name' => 'Fire drill']);
        $ada = $generator->create_and_enrol($course, 'student', ['firstname' => 'Ada']);
        $bob = $generator->create_and_enrol($course, 'student', ['firstname' => 'Bob']);
        $cy = $generator->create_and_enrol($course, 'student', ['firstname' => 'Cy']);

        // SCORM 1.2: Ada fails, then passes.
        $this->attempt($scorm, $ada->id, 1, ['cmi.core.lesson_status' => 'failed', 'cmi.core.score.raw' => '40',
            'cmi.core.total_time' => '0000:10:30.00'], 1000);
        $this->attempt($scorm, $ada->id, 2, ['cmi.core.lesson_status' => 'passed', 'cmi.core.score.raw' => '85.5',
            'cmi.core.total_time' => '0001:02:00'], 5000);
        // SCORM 2004: completed and failed is failed; time as an ISO 8601 duration; an empty score.
        $this->attempt($scorm, $bob->id, 1, ['cmi.completion_status' => 'completed', 'cmi.success_status' => 'failed',
            'cmi.score.raw' => '', 'cmi.total_time' => 'PT1H5M30S'], 2000);
        // Launched, nothing recorded yet.
        $this->attempt($scorm, $cy->id, 1, [], 3000);

        /** @var \core_reportbuilder_generator $rbgenerator */
        $rbgenerator = $generator->get_plugin_generator('core_reportbuilder');
        $report = $rbgenerator->create_report(['name' => 'SCORM', 'source' => scorm_attempts::class, 'default' => 0]);
        $columns = ['user:firstname', 'scorm_attempt:attempt', 'course:fullname', 'scorm_attempt:name',
            'scorm_attempt:status', 'scorm_attempt:scoreraw', 'scorm_attempt:totaltime', 'scorm_attempt:firstaccess',
            'scorm_attempt:lastaccess'];
        foreach ($columns as $i => $column) {
            $rbgenerator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => $column,
                'sortenabled' => $i < 2]);
        }

        $content = array_map('array_values', $this->get_custom_report_content($report->get('id')));
        $date = fn($time) => userdate($time);
        $this->assertEquals([
            ['Ada', 1, 'Safety', 'Fire drill', get_string('failed', 'scorm'), format_float(40, 2, true, true),
                format_time(630), $date(1000), $date(1002)],
            ['Ada', 2, 'Safety', 'Fire drill', get_string('passed', 'scorm'), format_float(85.5, 2, true, true),
                format_time(3720), $date(5000), $date(5002)],
            ['Bob', 1, 'Safety', 'Fire drill', get_string('failed', 'scorm'), '-', format_time(3930), $date(2000),
                $date(2003)],
            ['Cy', 1, 'Safety', 'Fire drill', get_string('notattempted', 'scorm'), '-', '-', get_string('never'),
                get_string('never')],
        ], $content);

        // Filter by status.
        $rbgenerator->create_filter(['reportid' => $report->get('id'), 'uniqueidentifier' => 'scorm_attempt:status']);
        $content = $this->get_custom_report_content($report->get('id'), 0, [
            'scorm_attempt:status_operator' => select::EQUAL_TO,
            'scorm_attempt:status_value' => scorm_attempt::STATUS_FAILED,
        ]);
        $this->assertEquals([['Ada', 1], ['Bob', 1]], array_map(fn($row) => array_slice(array_values($row), 0, 2), $content));
    }

    public function test_parse_total_time(): void {
        $this->assertSame(630, scorm_attempt::parse_total_time('0000:10:30.00'));
        $this->assertSame(3930, scorm_attempt::parse_total_time('PT1H5M30S'));
        $this->assertSame(90, scorm_attempt::parse_total_time('PT1M30.4S'));
        $this->assertSame(DAYSECS + 60, scorm_attempt::parse_total_time('P1DT1M'));
        $this->assertSame(690, scorm_attempt::parse_total_time('00:10:30,PT1M'), 'Several SCOs are added up');
        $this->assertSame(0, scorm_attempt::parse_total_time(''));
        $this->assertSame(0, scorm_attempt::parse_total_time('nonsense,PT'));
    }

    public function test_report_with_default_setup(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $scorm = $generator->create_module('scorm', ['course' => $course->id]);
        $user = $generator->create_and_enrol($course, 'student');
        $this->attempt($scorm, $user->id, 1, ['cmi.core.lesson_status' => 'completed'], 1000);

        $report = $generator->get_plugin_generator('core_reportbuilder')->create_report(
            ['name' => 'Default', 'source' => scorm_attempts::class]
        );
        $this->assertNotNull(manager::get_report_from_persistent($report));
        $content = $this->get_custom_report_content($report->get('id'));
        $this->assertCount(1, $content);
    }
}
