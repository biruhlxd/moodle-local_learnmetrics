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
 * Figures for the dashboard of one course: the site dashboard's figures for the course, and views per activity.
 *
 * In a course in separate groups mode, a teacher who cannot access all groups sees only the members of their groups.
 *
 * @package    local_learnmetrics
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_dashboard extends dashboard {
    /**
     * Constructor.
     *
     * @param int $days Length of the period, ending now.
     * @param int $courseid The course.
     * @param int[]|null $groupids Only members of these groups, null for everyone; see {@see get_group_limit()}.
     * @param int|null $now The end of the period; now by default.
     */
    public function __construct(int $days, int $courseid, ?array $groupids = null, ?int $now = null) {
        parent::__construct($days, 0, 0, $now);
        $this->set_course($courseid);
        $this->set_groups($groupids);
    }

    /**
     * The groups whose members a user may see in a course.
     *
     * @param \stdClass $course
     * @param int $userid
     * @return int[]|null The user's groups in a course in separate groups mode when they cannot access all groups,
     *     otherwise null (everyone).
     */
    public static function get_group_limit(\stdClass $course, int $userid): ?array {
        $context = \context_course::instance($course->id);
        if (
            groups_get_course_groupmode($course) != SEPARATEGROUPS
                || has_capability('moodle/site:accessallgroups', $context, $userid)
        ) {
            return null;
        }
        return array_keys(groups_get_all_groups($course->id, $userid, $course->defaultgroupingid));
    }

    /**
     * Views, learners and learning time per activity in the period.
     *
     * A view is a logged "viewed" action in the activity, as in Moodle's own activity reports; other read actions,
     * such as launching a SCORM package or reading a forum discussion, are not counted. The learning time is the time
     * the learning time engine counted in the activity (recorded from version 0.13.0).
     *
     * @param \course_modinfo $modinfo The course, as seen by the current user: activities they cannot see are left out.
     * @return \stdClass[] In course order: cmid, name, modname, modfullname, iconurl, url, views, learners, timespent.
     */
    public function get_activity_views(\course_modinfo $modinfo): array {
        global $DB, $CFG;
        [$scope, $params] = $this->scope('l.courseid', 'l.userid');
        $params += [
            'courseid' => $this->courseid,
            'modlevel' => CONTEXT_MODULE,
            'crud' => 'r',
            'action' => 'viewed',
            'from' => $this->from,
            'to' => $this->to,
            'guest' => $CFG->siteguest,
        ];
        $views = $DB->get_records_sql(
            "SELECT l.contextinstanceid AS cmid, COUNT(1) AS views, COUNT(DISTINCT l.userid) AS learners
               FROM {logstore_standard_log} l
              WHERE l.courseid = :courseid AND l.contextlevel = :modlevel AND l.crud = :crud AND l.action = :action
                AND l.timecreated >= :from AND l.timecreated <= :to
                AND l.anonymous = 0 AND l.userid > 0 AND l.userid <> :guest AND l.realuserid IS NULL $scope
           GROUP BY l.contextinstanceid",
            $params
        );
        [$scope, $params] = $this->scope('t.courseid', 't.userid');
        $times = $DB->get_records_sql_menu(
            "SELECT t.cmid, SUM(t.timespent)
               FROM {local_learnmetrics_cmtime} t
              WHERE t.courseid = :courseid AND t.day >= :from AND t.day <= :to $scope
           GROUP BY t.cmid",
            $params + ['courseid' => $this->courseid, 'from' => $this->from, 'to' => $this->to]
        );

        $activities = [];
        foreach ($modinfo->get_cms() as $cm) {
            if (!$cm->uservisible || $cm->deletioninprogress) {
                continue;
            }
            $activities[] = (object)[
                'cmid' => (int)$cm->id,
                'name' => $cm->get_formatted_name(),
                'modname' => $cm->modname,
                'modfullname' => $cm->get_module_type_name(),
                'iconurl' => $cm->get_icon_url(),
                'url' => $cm->url,
                'views' => isset($views[$cm->id]) ? (int)$views[$cm->id]->views : 0,
                'learners' => isset($views[$cm->id]) ? (int)$views[$cm->id]->learners : 0,
                'timespent' => (int)($times[$cm->id] ?? 0),
            ];
        }
        return $activities;
    }

    /**
     * The time each SCORM package of the course reported for its learners, for the attempts used in the period.
     *
     * A package reports the time spent in an attempt itself (cmi.core.total_time or cmi.total_time); Moodle adds
     * up the time of each visit. This is the package's own figure, shown next to the time measured from the log
     * and the "still here" pings. An attempt counts when any of its values was saved in the period, with its
     * whole time, which can include earlier visits. Packages that never report time are flagged.
     *
     * @param \course_modinfo $modinfo The course, as seen by the current user: packages they cannot see are left out.
     * @return \stdClass[] In course order: cmid, name, url, learners, attempts, reportedattempts, seconds,
     *     secondsperlearner, noreport (attempts but no time reported).
     */
    public function get_scorm_time(\course_modinfo $modinfo): array {
        global $DB;
        $cms = [];
        foreach ($modinfo->get_instances_of('scorm') as $cm) {
            if ($cm->uservisible && !$cm->deletioninprogress) {
                $cms[(int)$cm->instance] = $cm;
            }
        }
        if (!$cms) {
            return [];
        }
        [$scope, $params] = $this->scope('s.course', 'a.userid');
        [$insql, $inparams] = $DB->get_in_or_equal(array_keys($cms), SQL_PARAMS_NAMED, 'lmscorm');
        $params += $inparams + ['from' => $this->from, 'to' => $this->to];
        $attempts = $DB->get_records_sql(
            "SELECT a.id, a.scormid, a.userid
               FROM {scorm_attempt} a
               JOIN {scorm} s ON s.id = a.scormid
              WHERE a.scormid $insql
                AND EXISTS (SELECT 1 FROM {scorm_scoes_value} lmsv
                             WHERE lmsv.attemptid = a.id AND lmsv.timemodified >= :from AND lmsv.timemodified <= :to)
                    $scope",
            $params
        );

        // The time of each attempt: one value per SCO of the package, added up.
        $seconds = [];
        foreach (array_chunk(array_keys($attempts), 1000) as $chunk) {
            [$attemptsql, $attemptparams] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED, 'lmattempt');
            [$elementsql, $elementparams] = $DB->get_in_or_equal(
                \local_learnmetrics\reportbuilder\local\entities\scorm_attempt::TIME_ELEMENTS,
                SQL_PARAMS_NAMED,
                'lmelement'
            );
            $values = $DB->get_recordset_sql(
                "SELECT v.id, v.attemptid, v.value
                   FROM {scorm_scoes_value} v
                   JOIN {scorm_element} e ON e.id = v.elementid
                  WHERE v.attemptid $attemptsql AND e.element $elementsql",
                $attemptparams + $elementparams
            );
            foreach ($values as $value) {
                $seconds[$value->attemptid] = ($seconds[$value->attemptid] ?? 0)
                    + \local_learnmetrics\reportbuilder\local\entities\scorm_attempt::parse_total_time((string)$value->value);
            }
            $values->close();
        }

        $packages = [];
        foreach ($cms as $scormid => $cm) {
            $mine = array_filter($attempts, fn($attempt) => (int)$attempt->scormid === $scormid);
            $learners = count(array_unique(array_column($mine, 'userid')));
            $total = 0;
            $reported = 0;
            foreach ($mine as $attempt) {
                if (!empty($seconds[$attempt->id])) {
                    $total += $seconds[$attempt->id];
                    $reported++;
                }
            }
            $packages[] = (object)[
                'cmid' => (int)$cm->id,
                'name' => $cm->get_formatted_name(),
                'url' => $cm->url,
                'learners' => $learners,
                'attempts' => count($mine),
                'reportedattempts' => $reported,
                'seconds' => $total,
                'secondsperlearner' => $learners ? (int)round($total / $learners) : 0,
                'noreport' => count($mine) > 0 && $reported === 0,
            ];
        }
        $order = array_flip(array_map('intval', array_keys($modinfo->get_cms())));
        usort($packages, fn($a, $b) => $order[$a->cmid] <=> $order[$b->cmid]);
        return $packages;
    }
}
