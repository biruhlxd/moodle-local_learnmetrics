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

use core_external\external_api;
use local_learnmetrics\external\heartbeat;

/**
 * Tests for the "still here" pings: the web service, the pages that send them and their privacy.
 *
 * @package    local_learnmetrics
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(heartbeat::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(hook_callbacks::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(privacy\provider::class)]
final class heartbeat_test extends \core_privacy\tests\provider_testcase {
    /**
     * Call the web service as the current user.
     *
     * @param int $courseid
     * @return bool Whether the ping was stored.
     */
    private function ping(int $courseid): bool {
        $result = heartbeat::execute($courseid);
        return external_api::clean_returnvalue(heartbeat::execute_returns(), $result)['recorded'];
    }

    public function test_web_service(): void {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $student = $generator->create_and_enrol($course, 'student');
        $this->setUser($student);

        $this->assertTrue($this->ping($course->id));
        $this->assertFalse($this->ping($course->id), 'At most one ping in 50 seconds');
        $this->assertSame(1, $DB->count_records('local_learnmetrics_ping', ['userid' => $student->id,
            'courseid' => $course->id]));

        $DB->set_field('local_learnmetrics_ping', 'timecreated', time() - 50, ['userid' => $student->id]);
        $this->assertTrue($this->ping($course->id));
        $this->assertSame(2, $DB->count_records('local_learnmetrics_ping', ['userid' => $student->id]));

        set_config('heartbeat', 0, 'local_learnmetrics');
        $DB->delete_records('local_learnmetrics_ping');
        $this->assertFalse($this->ping($course->id), 'Pings are off');
        $this->assertSame(0, $DB->count_records('local_learnmetrics_ping'));
    }

    public function test_web_service_needs_course_access(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $this->setUser($this->getDataGenerator()->create_user());
        $this->expectException(\require_login_exception::class);
        heartbeat::execute($course->id);
    }

    public function test_pages_that_send_pings(): void {
        global $PAGE;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $module = $generator->create_module('page', ['course' => $course->id]);
        $student = $generator->create_and_enrol($course, 'student');
        $this->setUser($student);

        $page = new \moodle_page();
        $page->set_context(\context_module::instance($module->cmid));
        $this->assertSame((int)$course->id, hook_callbacks::get_ping_courseid($page), 'An activity of the course');

        $page = new \moodle_page();
        $page->set_context(\context_system::instance());
        $this->assertSame(0, hook_callbacks::get_ping_courseid($page), 'Not in a course');

        $page = new \moodle_page();
        $page->set_context(\context_course::instance($course->id));
        set_config('heartbeat', 0, 'local_learnmetrics');
        $this->assertSame(0, hook_callbacks::get_ping_courseid($page), 'Pings are off');
        set_config('heartbeat', 1, 'local_learnmetrics');

        $this->setGuestUser();
        $this->assertSame(0, hook_callbacks::get_ping_courseid($page), 'Guests');
        $this->setUser(null);
        $this->assertSame(0, hook_callbacks::get_ping_courseid($page), 'Not logged in');

        // The hook adds the module to the page.
        $this->setUser($student);
        $PAGE = $page;
        hook_callbacks::before_footer_html_generation(
            new \core\hook\output\before_footer_html_generation($PAGE->get_renderer('core'))
        );
        $this->assertStringContainsString('local_learnmetrics/heartbeat', $PAGE->requires->get_end_code());
    }

    public function test_privacy_of_unprocessed_pings(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $other = $this->getDataGenerator()->create_and_enrol($course, 'student');
        foreach ([$user, $other] as $u) {
            $DB->insert_record('local_learnmetrics_ping', (object)['userid' => $u->id, 'courseid' => $course->id,
                'timecreated' => time()]);
        }
        $context = \context_course::instance($course->id);

        $contexts = privacy\provider::get_contexts_for_userid($user->id);
        $this->assertContains((int)$context->id, array_map('intval', $contexts->get_contextids()));
        $userlist = new \core_privacy\local\request\userlist($context, 'local_learnmetrics');
        privacy\provider::get_users_in_context($userlist);
        $this->assertEqualsCanonicalizing([$user->id, $other->id], $userlist->get_userids());

        $approved = new \core_privacy\local\request\approved_contextlist($user, 'local_learnmetrics', [$context->id]);
        privacy\provider::export_user_data($approved);
        $writer = \core_privacy\local\request\writer::with_context($context);
        $this->assertTrue($writer->has_any_data());

        privacy\provider::delete_data_for_user($approved);
        $this->assertSame(0, $DB->count_records('local_learnmetrics_ping', ['userid' => $user->id]));
        $this->assertSame(1, $DB->count_records('local_learnmetrics_ping', ['userid' => $other->id]));
        privacy\provider::delete_data_for_all_users_in_context($context);
        $this->assertSame(0, $DB->count_records('local_learnmetrics_ping'));
    }
}
