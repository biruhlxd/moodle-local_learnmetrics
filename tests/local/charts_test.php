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
 * Tests that every dashboard chart can be built and rendered, with and without data.
 *
 * @package    local_learnmetrics
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(charts::class)]
final class charts_test extends \advanced_testcase {
    /**
     * Render every chart and check the output.
     *
     * @param \core\chart_base[] $charts
     */
    private function assert_renders(array $charts): void {
        global $OUTPUT;
        foreach ($charts as $name => $chart) {
            $html = $OUTPUT->render($chart);
            $this->assertStringContainsString('chart-area', $html, $name);
            // The chart data is valid JSON for the chart library.
            $this->assertNotEmpty(json_encode($chart), $name);
        }
    }

    public function test_site_dashboard_charts_render(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $now = time();
        $DB->insert_record('local_learnmetrics_daily', (object)['userid' => $user->id, 'courseid' => $course->id,
            'day' => (new time_tracker())->day_start($now), 'timespent' => 3600, 'events' => 5]);

        $charts = charts::for_dashboard(new dashboard(30, 0, 0, $now));
        $this->assertSame(['trend', 'completion', 'weekday', 'topcourses'], array_keys($charts));
        $this->assertCount(2, $charts['trend']->get_yaxes(), 'Learners on the left axis, hours on the right');
        $this->assert_renders($charts);
    }

    public function test_charts_render_without_data(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $charts = charts::for_dashboard(new dashboard(7));
        $this->assertArrayNotHasKey('topcourses', $charts, 'No chart of top courses without learning time');
        $this->assert_renders($charts);
    }
}
