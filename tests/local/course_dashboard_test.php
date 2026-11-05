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
 * Tests for the course dashboard: figures of one course, groups, views per activity and navigation.
 *
 * @package    local_learnmetrics
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(course_dashboard::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(dashboard::class)]
final class course_dashboard_test extends \advanced_testcase {
    /** @var \stdClass The course, in separate groups mode. */
    private $course;

    /** @var \stdClass Another course. */
    private $other;

    /** @var \stdClass[] Users: ann and ben (learners in groups A and B), tom (teacher in group A), ed (editing teacher). */
    private $users = [];

    /** @var \stdClass[] Groups: a, b. */
    private $groups = [];

    /** @var int The end of the period of the tests. */
    private $now;

    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        $this->now = time() + MINSECS;
        $generator = $this->getDataGenerator();
        $this->course = $generator->create_course(['groupmode' => SEPARATEGROUPS, 'groupmodeforce' => 1]);
        $this->other = $generator->create_course();
        $this->users['ann'] = $generator->create_and_enrol($this->course, 'student', ['firstname' => 'Ann']);
        $this->users['ben'] = $generator->create_and_enrol($this->course, 'student', ['firstname' => 'Ben']);
        $this->users['tom'] = $generator->create_and_enrol($this->course, 'teacher', ['firstname' => 'Tom']);
        $this->users['ed'] = $generator->create_and_enrol($this->course, 'editingteacher', ['firstname' => 'Ed']);
        $generator->enrol_user($this->users['ann']->id, $this->other->id, 'student');
        foreach (['a', 'b'] as $name) {
            $this->groups[$name] = $generator->create_group(['courseid' => $this->course->id]);
        }
        $generator->create_group_member(['groupid' => $this->groups['a']->id, 'userid' => $this->users['ann']->id]);
        $generator->create_group_member(['groupid' => $this->groups['b']->id, 'userid' => $this->users['ben']->id]);
        $generator->create_group_member(['groupid' => $this->groups['a']->id, 'userid' => $this->users['tom']->id]);

        $day = (new time_tracker())->day_start($this->now);
        foreach ([['ann', $this->course, 1800], ['ben', $this->course, 3600], ['ann', $this->other, 7200]] as [$user, $c, $s]) {
            $DB->insert_record('local_learnmetrics_daily', (object)['userid' => $this->users[$user]->id,
                'courseid' => $c->id, 'day' => $day, 'timespent' => $s, 'events' => 3]);
        }
    }

    /**
     * Write a log entry in an activity.
     *
     * @param int $userid
     * @param \cm_info $cm
     * @param string $crud
     * @param array $extra Other fields.
     */
    private function log(int $userid, \cm_info $cm, string $crud = 'r', array $extra = []): void {
        global $DB;
        $DB->insert_record('logstore_standard_log', (object)($extra + [
            'eventname' => '\mod_page\event\course_module_viewed',
            'component' => 'mod_' . $cm->modname,
            'action' => 'viewed',
            'target' => 'course_module',
            'crud' => $crud,
            'edulevel' => 2,
            'contextid' => $cm->context->id,
            'contextlevel' => CONTEXT_MODULE,
            'contextinstanceid' => $cm->id,
            'userid' => $userid,
            'courseid' => $cm->course,
            'anonymous' => 0,
            'timecreated' => $this->now - HOURSECS,
            'origin' => 'web',
        ]));
    }

    public function test_figures_of_one_course(): void {
        $dashboard = new course_dashboard(30, $this->course->id, null, $this->now);
        $summary = $dashboard->get_summary();
        $this->assertSame(2, $summary['activelearners']);
        $this->assertEquals(1.5, $summary['hours'], 'Time in the other course is not counted');
        $this->assertSame(2, $summary['enrolments'], 'Learners only: the teachers are not counted');
        $this->assertSame(['completed' => 0, 'inprogress' => 0, 'notstarted' => 2], $dashboard->get_completion_status());
        $this->assertSame(
            ['learners' => 2, 'hours' => 1.5],
            $dashboard->get_daily_trend()[(new time_tracker())->day_start($this->now)]
        );
    }

    public function test_group_limit(): void {
        $this->assertNull(
            course_dashboard::get_group_limit($this->course, $this->users['ed']->id),
            'Editing teachers can access all groups'
        );
        $this->assertSame(
            [(int)$this->groups['a']->id],
            array_map('intval', course_dashboard::get_group_limit($this->course, $this->users['tom']->id))
        );
        $this->assertNull(course_dashboard::get_group_limit($this->other, $this->users['ann']->id), 'No groups');

        $groupa = new course_dashboard(30, $this->course->id, [$this->groups['a']->id], $this->now);
        $this->assertSame(1, $groupa->get_summary()['activelearners']);
        $this->assertEquals(0.5, $groupa->get_summary()['hours']);
        $this->assertSame(['Ann'], array_column($groupa->get_learners(dashboard::METRIC_ACTIVE), 'firstname'));

        $nogroup = new course_dashboard(30, $this->course->id, [], $this->now);
        $this->assertSame(0, $nogroup->get_summary()['activelearners']);
        $this->assertSame([], $nogroup->get_learners(dashboard::METRIC_ENROLMENTS));
    }

    public function test_activity_views(): void {
        $generator = $this->getDataGenerator();
        $page = $generator->create_module('page', ['course' => $this->course->id, 'name' => 'Reading']);
        $generator->create_module('url', ['course' => $this->course->id, 'name' => 'Never opened']);
        // The activities as the editing teacher sees them.
        $this->setUser($this->users['ed']);
        $modinfo = get_fast_modinfo($this->course);
        $cm = $modinfo->get_cm($page->cmid);
        $this->log($this->users['ann']->id, $cm);
        $this->log($this->users['ann']->id, $cm);
        $this->log($this->users['ben']->id, $cm);
        $this->log($this->users['ben']->id, $cm, 'u');                                 // Not a view.
        $this->log($this->users['ben']->id, $cm, 'r', ['action' => 'launched']);       // Read, but not a view.
        $this->log($this->users['ben']->id, $cm, 'r', ['realuserid' => get_admin()->id]); // Logged in as.
        $this->log($this->users['ben']->id, $cm, 'r', ['timecreated' => $this->now - 60 * DAYSECS]); // Before the period.

        $activities = (new course_dashboard(30, $this->course->id, null, $this->now))->get_activity_views($modinfo);
        $this->assertSame(['Reading', 'Never opened'], array_column($activities, 'name'));
        $this->assertSame([3, 0], array_column($activities, 'views'));
        $this->assertSame([2, 0], array_column($activities, 'learners'));
        $this->assertSame(['page', 'url'], array_column($activities, 'modname'));

        $groupb = new course_dashboard(30, $this->course->id, [$this->groups['b']->id], $this->now);
        $this->assertSame([1, 0], array_column($groupb->get_activity_views($modinfo), 'views'));
    }

    public function test_charts_render(): void {
        global $OUTPUT;
        $this->setUser($this->users['ed']);
        $charts = charts::for_dashboard(new course_dashboard(30, $this->course->id, null, $this->now), false);
        $this->assertSame(['trend', 'completion', 'weekday'], array_keys($charts));
        foreach ($charts as $name => $chart) {
            $this->assertStringContainsString('chart-area', $OUTPUT->render($chart), $name);
        }
    }

    public function test_capability_and_navigation(): void {
        global $CFG, $PAGE;
        require_once($CFG->dirroot . '/local/learnmetrics/lib.php');
        $context = \context_course::instance($this->course->id);
        $PAGE->set_url('/course/view.php', ['id' => $this->course->id]);

        foreach (['tom' => true, 'ed' => true, 'ann' => false] as $user => $allowed) {
            $this->setUser($this->users[$user]);
            $this->assertSame($allowed, has_capability('local/learnmetrics:viewcoursedashboard', $context), $user);
            $node = \navigation_node::create('Course administration');
            $node->add('Reports', null, \navigation_node::TYPE_CONTAINER, null, 'coursereports');
            local_learnmetrics_extend_navigation_course($node, $this->course, $context);
            $this->assertSame($allowed, (bool)$node->find('local_learnmetrics_course', \navigation_node::TYPE_SETTING), $user);
            if ($allowed) {
                $this->assertNotFalse($node->get('coursereports')->get('local_learnmetrics_course'), 'In the Reports menu');
            }
        }
    }

    /**
     * Save values of a SCORM attempt, as mod_scorm stores them (Moodle 4.3 and later).
     *
     * @param \stdClass $scorm
     * @param int $userid
     * @param int $attempt
     * @param array $values Element => value.
     * @param int $time When the values were saved.
     */
    private function track(\stdClass $scorm, int $userid, int $attempt, array $values, int $time): void {
        global $DB;
        $scoid = (int)$DB->get_field_select(
            'scorm_scoes',
            'id',
            'scorm = ? AND scormtype = ?',
            [$scorm->id, 'sco'],
            IGNORE_MULTIPLE
        );
        $attemptid = $DB->insert_record('scorm_attempt', (object)['userid' => $userid, 'scormid' => $scorm->id,
            'attempt' => $attempt]);
        foreach ($values as $element => $value) {
            $elementid = $DB->get_field('scorm_element', 'id', ['element' => $element])
                ?: $DB->insert_record('scorm_element', (object)['element' => $element]);
            $DB->insert_record('scorm_scoes_value', (object)['scoid' => $scoid, 'attemptid' => $attemptid,
                'elementid' => $elementid, 'value' => $value, 'timemodified' => $time]);
        }
    }

    public function test_scorm_time(): void {
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $package = $generator->create_module('scorm', ['course' => $this->course->id, 'name' => 'Package']);
        $silent = $generator->create_module('scorm', ['course' => $this->course->id, 'name' => 'Silent']);
        $recent = $this->now - HOURSECS;

        // SCORM 1.2 and SCORM 2004 time formats.
        $this->track($package, $this->users['ann']->id, 1, ['cmi.core.lesson_status' => 'passed',
            'cmi.core.total_time' => '00:12:00.00'], $recent);
        $this->track($package, $this->users['ben']->id, 1, ['cmi.completion_status' => 'incomplete',
            'cmi.total_time' => 'PT20M'], $recent);
        // Not counted: a teacher, and an attempt last used before the period.
        $this->track($package, $this->users['tom']->id, 1, ['cmi.core.total_time' => '01:00:00'], $recent);
        $this->track($package, $this->users['ann']->id, 2, ['cmi.core.total_time' => '05:00:00'], $this->now - 60 * DAYSECS);
        // A package that never reports time.
        $this->track($silent, $this->users['ben']->id, 1, ['cmi.core.lesson_status' => 'completed'], $recent);

        $this->setUser($this->users['ed']);
        $modinfo = get_fast_modinfo($this->course);
        $packages = (new course_dashboard(30, $this->course->id, null, $this->now))->get_scorm_time($modinfo);
        $this->assertSame(['Package', 'Silent'], array_column($packages, 'name'));
        $this->assertSame([2, 1], array_column($packages, 'learners'));
        $this->assertSame([2, 1], array_column($packages, 'attempts'));
        $this->assertSame([1920, 0], array_column($packages, 'seconds'));
        $this->assertSame([960, 0], array_column($packages, 'secondsperlearner'));
        $this->assertSame([false, true], array_column($packages, 'noreport'));

        // A teacher limited to group B sees only Ben.
        $groupb = new course_dashboard(30, $this->course->id, [$this->groups['b']->id], $this->now);
        $this->assertSame([1200, 0], array_column($groupb->get_scorm_time($modinfo), 'seconds'));
    }
}
