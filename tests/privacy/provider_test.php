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

namespace local_learnmetrics\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use local_learnmetrics\local\time_tracker;

/**
 * Tests for the privacy provider: learning time, pings and the last action of a user.
 *
 * @package    local_learnmetrics
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(provider::class)]
final class provider_test extends \core_privacy\tests\provider_testcase {
    /** @var \stdClass The course. */
    private $course;

    /** @var \stdClass A learner with learning time. */
    private $learner;

    /** @var \stdClass Another learner with learning time. */
    private $other;

    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $this->course = $generator->create_course();
        $page = $generator->create_module('page', ['course' => $this->course->id, 'name' => 'Welcome']);
        $day = (new time_tracker())->day_start(time());
        foreach (['learner', 'other'] as $name) {
            $user = $generator->create_and_enrol($this->course, 'student');
            $this->$name = $user;
            $DB->insert_record('local_learnmetrics_daily', (object)['userid' => $user->id, 'courseid' => $this->course->id,
                'day' => $day, 'timespent' => 1800, 'events' => 3]);
            $DB->insert_record('local_learnmetrics_cmtime', (object)['userid' => $user->id, 'courseid' => $this->course->id,
                'cmid' => $page->cmid, 'day' => $day, 'timespent' => 1200, 'events' => 2]);
            $DB->insert_record('local_learnmetrics_course', (object)['userid' => $user->id, 'courseid' => $this->course->id,
                'timespent' => 1800, 'events' => 3, 'activedays' => 1, 'firstactivity' => $day, 'lastactivity' => $day + 60]);
            $DB->insert_record('local_learnmetrics_session', (object)['userid' => $user->id, 'courseid' => $this->course->id,
                'timecreated' => $day + 60, 'web' => 1, 'cmid' => $page->cmid]);
            $DB->insert_record('local_learnmetrics_ping', (object)['userid' => $user->id, 'courseid' => $this->course->id,
                'timecreated' => $day + 120]);
        }
    }

    public function test_metadata(): void {
        $tables = array_map(
            fn($item) => $item->get_name(),
            provider::get_metadata(new collection('local_learnmetrics'))->get_collection()
        );
        foreach (['daily', 'cmtime', 'course', 'session', 'ping'] as $table) {
            $this->assertContains('local_learnmetrics_' . $table, $tables);
        }
    }

    public function test_contexts_and_users(): void {
        $coursecontext = \context_course::instance($this->course->id);
        $usercontext = \context_user::instance($this->learner->id);
        $contextids = array_map('intval', provider::get_contexts_for_userid($this->learner->id)->get_contextids());
        sort($contextids);
        $expected = [(int)$coursecontext->id, (int)$usercontext->id];
        sort($expected);
        $this->assertSame($expected, $contextids);

        $userlist = new userlist($coursecontext, 'local_learnmetrics');
        provider::get_users_in_context($userlist);
        $userids = array_map('intval', $userlist->get_userids());
        sort($userids);
        $this->assertSame([(int)$this->learner->id, (int)$this->other->id], $userids);

        $userlist = new userlist($usercontext, 'local_learnmetrics');
        provider::get_users_in_context($userlist);
        $this->assertSame([(int)$this->learner->id], array_map('intval', $userlist->get_userids()));
    }

    public function test_export(): void {
        $context = \context_course::instance($this->course->id);
        $this->export_context_data_for_user($this->learner->id, $context, 'local_learnmetrics');
        $data = writer::with_context($context)->get_data([get_string('pluginname', 'local_learnmetrics')]);
        $this->assertSame(format_time(1800), $data->timespent);
        $this->assertSame('Welcome', $data->activities[0]['activity']);
        $this->assertCount(1, $data->days);
        $pings = writer::with_context($context)->get_data([
            get_string('pluginname', 'local_learnmetrics'),
            get_string('privacy:pings', 'local_learnmetrics'),
        ]);
        $this->assertCount(1, $pings->pings);

        $context = \context_user::instance($this->learner->id);
        $this->export_context_data_for_user($this->learner->id, $context, 'local_learnmetrics');
        $this->assertNotEmpty(writer::with_context($context)->get_data([get_string('pluginname', 'local_learnmetrics')]));
    }

    public function test_delete_for_user(): void {
        global $DB;
        $coursecontext = \context_course::instance($this->course->id);
        $usercontext = \context_user::instance($this->learner->id);
        provider::delete_data_for_user(new approved_contextlist(
            $this->learner,
            'local_learnmetrics',
            [$coursecontext->id, $usercontext->id]
        ));
        foreach (['daily', 'cmtime', 'course', 'session', 'ping'] as $table) {
            $this->assertFalse($DB->record_exists('local_learnmetrics_' . $table, ['userid' => $this->learner->id]));
            $this->assertTrue($DB->record_exists('local_learnmetrics_' . $table, ['userid' => $this->other->id]));
        }
    }

    public function test_delete_for_users_and_all(): void {
        global $DB;
        $coursecontext = \context_course::instance($this->course->id);
        provider::delete_data_for_users(new approved_userlist($coursecontext, 'local_learnmetrics', [$this->other->id]));
        $this->assertFalse($DB->record_exists('local_learnmetrics_course', ['userid' => $this->other->id]));
        $this->assertTrue($DB->record_exists('local_learnmetrics_course', ['userid' => $this->learner->id]));

        provider::delete_data_for_all_users_in_context($coursecontext);
        foreach (['daily', 'cmtime', 'course', 'ping'] as $table) {
            $this->assertFalse($DB->record_exists('local_learnmetrics_' . $table, ['courseid' => $this->course->id]));
        }
        $this->assertTrue($DB->record_exists('local_learnmetrics_session', ['userid' => $this->learner->id]));
    }
}
