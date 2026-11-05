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
 * Activity completion of a course: the learners by the activities with completion tracking.
 *
 * The learners are those of the course dashboard (a learner role and an active enrolment; their groups only, for a
 * teacher who cannot access all groups in separate groups mode). The activities are those the viewer can see.
 *
 * @package    local_learnmetrics
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class completion_grid {
    /** @var string Not completed. */
    const STATE_NONE = 'none';

    /** @var string Completed (without a pass grade, or passed). */
    const STATE_COMPLETE = 'complete';

    /** @var string Completed with a failing grade. */
    const STATE_FAIL = 'fail';

    /** @var course_dashboard The learners, through the course dashboard. */
    protected $dashboard;

    /** @var \course_modinfo The course, as the viewer sees it. */
    protected $modinfo;

    /**
     * Constructor.
     *
     * @param course_dashboard $dashboard The dashboard of the course, with the viewer's groups.
     * @param \course_modinfo $modinfo The course, as the viewer sees it.
     */
    public function __construct(course_dashboard $dashboard, \course_modinfo $modinfo) {
        global $CFG;
        require_once($CFG->libdir . '/completionlib.php');
        $this->dashboard = $dashboard;
        $this->modinfo = $modinfo;
    }

    /**
     * The activities with completion tracking that the viewer can see, in course order.
     *
     * @return \cm_info[] By course module id.
     */
    public function get_activities(): array {
        $activities = [];
        if (!$this->modinfo->get_course()->enablecompletion) {
            return [];
        }
        foreach ($this->modinfo->get_cms() as $cm) {
            if ($cm->completion != COMPLETION_TRACKING_NONE && $cm->uservisible && !$cm->deletioninprogress) {
                $activities[(int)$cm->id] = $cm;
            }
        }
        return $activities;
    }

    /**
     * The learners, by name.
     *
     * @return \stdClass[] By user id: id and the name fields.
     */
    public function get_learners(): array {
        global $DB;
        $userids = $this->dashboard->get_enrolled_learners();
        if (!$userids) {
            return [];
        }
        $fields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $learners = $DB->get_records_sql("SELECT u.id, $fields FROM {user} u WHERE u.id $insql", $params);
        uasort($learners, fn($a, $b) => strcmp(\core_text::strtolower(fullname($a)), \core_text::strtolower(fullname($b))));
        return $learners;
    }

    /**
     * The completion state of each learner in each activity.
     *
     * @param int[] $userids
     * @return string[][] User id => course module id => one of the STATE_ constants; only completed ones are set.
     */
    public function get_states(array $userids): array {
        global $DB;
        $cmids = array_keys($this->get_activities());
        if (!$userids || !$cmids) {
            return [];
        }
        $states = [];
        foreach (array_chunk($userids, 1000) as $chunk) {
            [$usersql, $userparams] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED, 'lmuser');
            [$cmsql, $cmparams] = $DB->get_in_or_equal($cmids, SQL_PARAMS_NAMED, 'lmcm');
            $rows = $DB->get_recordset_select(
                'course_modules_completion',
                "userid $usersql AND coursemoduleid $cmsql AND completionstate > 0",
                $userparams + $cmparams,
                '',
                'id, userid, coursemoduleid, completionstate'
            );
            foreach ($rows as $row) {
                $states[(int)$row->userid][(int)$row->coursemoduleid] = (int)$row->completionstate === COMPLETION_COMPLETE_FAIL
                    ? self::STATE_FAIL : self::STATE_COMPLETE;
            }
            $rows->close();
        }
        return $states;
    }

    /**
     * The grid: learners, activities, each learner's completion and each activity's completion rate.
     *
     * @return array activities (cmid, name, completed, percent), learners (id, fullname, completed, percent,
     *     states: state by activity in course order)
     */
    public function get_grid(): array {
        $activities = $this->get_activities();
        $learners = $this->get_learners();
        $states = $this->get_states(array_keys($learners));
        $count = count($learners);
        $columns = [];
        foreach ($activities as $cmid => $cm) {
            $completed = count(array_filter($states, fn($bycm) => ($bycm[$cmid] ?? null) === self::STATE_COMPLETE));
            $columns[] = (object)[
                'cmid' => $cmid,
                'name' => $cm->get_formatted_name(),
                'completed' => $completed,
                'percent' => $count ? (int)round(100 * $completed / $count) : 0,
            ];
        }
        $rows = [];
        foreach ($learners as $userid => $learner) {
            $mine = [];
            foreach ($activities as $cmid => $cm) {
                $mine[] = $states[$userid][$cmid] ?? self::STATE_NONE;
            }
            $completed = count(array_filter($mine, fn($state) => $state === self::STATE_COMPLETE));
            $rows[] = (object)[
                'id' => (int)$userid,
                'fullname' => fullname($learner),
                'completed' => $completed,
                'percent' => $activities ? (int)round(100 * $completed / count($activities)) : 0,
                'states' => $mine,
            ];
        }
        return ['activities' => $columns, 'learners' => $rows];
    }
}
