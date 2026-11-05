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
 * Figures for the site dashboard: headline numbers, trends, top courses and learners needing attention.
 *
 * Every figure follows the same filters: a period, a course category (with its sub-categories) and a cohort.
 *
 * @package    local_learnmetrics
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dashboard {
    /** @var int[] Periods offered, in days. */
    const PERIODS = [7, 30, 90, 365];

    /** @var int A learner with no activity in a course for this long needs attention, in days. */
    const INACTIVE_DAYS = 14;

    /** @var string Drill-down: learners with learning time in the period. */
    const METRIC_ACTIVE = 'activelearners';

    /** @var string Drill-down: enrolments created in the period. */
    const METRIC_ENROLMENTS = 'enrolments';

    /** @var string Drill-down: course completions in the period. */
    const METRIC_COMPLETIONS = 'completions';

    /** @var string[] Drill-down lists offered. */
    const METRICS = [self::METRIC_ACTIVE, self::METRIC_ENROLMENTS, self::METRIC_COMPLETIONS];

    /** @var int Start of the period. */
    protected $from;

    /** @var int End of the period. */
    protected $to;

    /** @var int Course category, 0 for all. */
    protected $categoryid;

    /** @var int Cohort, 0 for everyone. */
    protected $cohortid;

    /** @var string Department (the value of the field chosen in the settings), '' for everyone. */
    protected $department = '';

    /** @var int Course, 0 for all. */
    protected $courseid = 0;

    /** @var int[]|null Only members of these groups, null for everyone. */
    protected $groupids = null;

    /**
     * Constructor.
     *
     * @param int $days Length of the period, ending now.
     * @param int $categoryid Course category, 0 for all.
     * @param int $cohortid Cohort, 0 for everyone.
     * @param int|null $now The end of the period; now by default.
     */
    public function __construct(int $days, int $categoryid = 0, int $cohortid = 0, ?int $now = null) {
        $tracker = new time_tracker();
        $this->to = $now ?? time();
        $this->from = $tracker->day_start($this->to - ($days - 1) * DAYSECS);
        $this->categoryid = $categoryid;
        $this->cohortid = $cohortid;
    }

    /**
     * Limit every figure to the learners of one department.
     *
     * @param string $department The value of the department field chosen in the settings, '' for everyone.
     * @return static
     */
    public function set_department(string $department): static {
        $this->department = $department;
        return $this;
    }

    /**
     * Limit every figure to one course.
     *
     * @param int $courseid Course, 0 for all.
     * @return static
     */
    public function set_course(int $courseid): static {
        $this->courseid = $courseid;
        return $this;
    }

    /**
     * Limit every figure to the members of some groups.
     *
     * @param int[]|null $groupids Groups, null for everyone; an empty array leaves nobody.
     * @return static
     */
    public function set_groups(?array $groupids): static {
        $this->groupids = $groupids === null ? null : array_map('intval', $groupids);
        return $this;
    }

    /**
     * Start of the period.
     *
     * @return int
     */
    public function get_from(): int {
        return $this->from;
    }

    /**
     * The course of the dashboard.
     *
     * @return int 0 for all courses.
     */
    public function get_courseid(): int {
        return $this->courseid;
    }

    /**
     * SQL that limits rows to the learners of the dashboard, for other plugins: see {@see scope()}.
     *
     * @param string $courseid Column with the course id.
     * @param string $userid Column with the user id.
     * @return array [sql starting with AND, or empty; params]
     */
    public function get_scope_sql(string $courseid, string $userid): array {
        return $this->scope($courseid, $userid);
    }

    /**
     * The roles of learners, from the setting; by default the roles with the student archetype.
     *
     * @return int[] Role ids; empty when no role is chosen, then every user is counted.
     */
    public static function get_learner_roleids(): array {
        $setting = get_config('local_learnmetrics', 'learnerroles');
        if ($setting === false) {
            return array_map('intval', array_keys(get_archetype_roles('student')));
        }
        return array_values(array_filter(array_map('intval', explode(',', $setting))));
    }

    /**
     * SQL that limits rows to learners, and to the chosen course, category, cohort, department and groups.
     *
     * Every figure and drill-down list goes through here, so they all count the same learners. Learners are users
     * with a learner role in the course of the row (in any course, for time spent outside courses, which is
     * recorded on the site course); site administrators are left out unless the setting includes them.
     *
     * @param string $courseid Column with the course id.
     * @param string $userid Column with the user id.
     * @return array [sql starting with AND, or empty; params]
     */
    protected function scope(string $courseid, string $userid): array {
        global $DB, $CFG;
        $sql = '';
        $params = [];
        $roleids = self::get_learner_roleids();
        if ($roleids) {
            [$insql, $inparams] = $DB->get_in_or_equal($roleids, SQL_PARAMS_NAMED, 'lmrole');
            $sql .= " AND EXISTS (SELECT 1
                                    FROM {role_assignments} lmsra
                                    JOIN {context} lmsctx ON lmsctx.id = lmsra.contextid AND lmsctx.contextlevel = :lmctxcourse
                                   WHERE lmsra.userid = $userid AND lmsra.roleid $insql
                                     AND (lmsctx.instanceid = $courseid OR $courseid = :lmsiteid))";
            $params += $inparams + ['lmctxcourse' => CONTEXT_COURSE, 'lmsiteid' => SITEID];
        }
        $admins = array_filter(array_map('intval', explode(',', $CFG->siteadmins ?? '')));
        if ($admins && !get_config('local_learnmetrics', 'includeadmins')) {
            [$insql, $inparams] = $DB->get_in_or_equal($admins, SQL_PARAMS_NAMED, 'lmadmin', false);
            $sql .= " AND $userid $insql";
            $params += $inparams;
        }
        if ($this->courseid) {
            $sql .= " AND $courseid = :lmcourse";
            $params['lmcourse'] = $this->courseid;
        }
        if ($this->categoryid) {
            $category = \core_course_category::get($this->categoryid, IGNORE_MISSING, true);
            $ids = $category ? array_merge([$category->id], $category->get_all_children_ids()) : [0];
            [$insql, $inparams] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'lmcat');
            $sql .= " AND EXISTS (SELECT 1 FROM {course} lmsc WHERE lmsc.id = $courseid AND lmsc.category $insql)";
            $params += $inparams;
        }
        if ($this->cohortid) {
            $sql .= " AND EXISTS (SELECT 1 FROM {cohort_members} lmscm WHERE lmscm.userid = $userid
                                     AND lmscm.cohortid = :lmcohort)";
            $params['lmcohort'] = $this->cohortid;
        }
        if ($this->department !== '') {
            [$departmentsql, $departmentparams] = department::sql($userid, $this->department);
            $sql .= $departmentsql;
            $params += $departmentparams;
        }
        if ($this->groupids !== null) {
            if ($this->groupids) {
                [$insql, $inparams] = $DB->get_in_or_equal($this->groupids, SQL_PARAMS_NAMED, 'lmgroup');
                $sql .= " AND EXISTS (SELECT 1 FROM {groups_members} lmsgm WHERE lmsgm.userid = $userid
                                         AND lmsgm.groupid $insql)";
                $params += $inparams;
            } else {
                $sql .= " AND 1 = 0";
            }
        }
        return [$sql, $params];
    }

    /**
     * Headline numbers.
     *
     * @return array activelearners, hours, hoursperlearner, enrolments, completions
     */
    public function get_summary(): array {
        global $DB;
        [$scope, $params] = $this->scope('d.courseid', 'd.userid');
        $params += ['from' => $this->from, 'to' => $this->to];
        $activity = $DB->get_record_sql(
            "SELECT COUNT(DISTINCT d.userid) AS learners, COALESCE(SUM(d.timespent), 0) AS seconds
               FROM {local_learnmetrics_daily} d
              WHERE d.day >= :from AND d.day <= :to $scope",
            $params
        );

        [$scope, $params] = $this->scope('e.courseid', 'ue.userid');
        $params += ['from' => $this->from, 'to' => $this->to, 'siteid' => SITEID];
        $enrolments = $DB->count_records_sql(
            "SELECT COUNT(1)
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
              WHERE ue.timecreated >= :from AND ue.timecreated <= :to AND e.courseid <> :siteid $scope",
            $params
        );

        [$scope, $params] = $this->scope('cc.course', 'cc.userid');
        $params += ['from' => $this->from, 'to' => $this->to];
        $completions = $DB->count_records_sql(
            "SELECT COUNT(1)
               FROM {course_completions} cc
              WHERE cc.timecompleted >= :from AND cc.timecompleted <= :to $scope",
            $params
        );

        $hours = $activity->seconds / HOURSECS;
        return [
            'activelearners' => (int)$activity->learners,
            'hours' => round($hours, 1),
            'hoursperlearner' => $activity->learners ? round($hours / $activity->learners, 1) : 0,
            'enrolments' => $enrolments,
            'completions' => $completions,
        ];
    }

    /**
     * Active learners and learning hours on each day of the period.
     *
     * @return array Day start => ['learners' => int, 'hours' => float], for every day, including quiet ones.
     */
    public function get_daily_trend(): array {
        global $DB;
        [$scope, $params] = $this->scope('d.courseid', 'd.userid');
        $params += ['from' => $this->from, 'to' => $this->to];
        $rows = $DB->get_records_sql(
            "SELECT d.day, COUNT(DISTINCT d.userid) AS learners, SUM(d.timespent) AS seconds
               FROM {local_learnmetrics_daily} d
              WHERE d.day >= :from AND d.day <= :to $scope
           GROUP BY d.day",
            $params
        );
        $trend = [];
        $tracker = new time_tracker();
        for ($day = $this->from; $day <= $this->to; $day = $tracker->day_start($day + DAYSECS + 2 * HOURSECS)) {
            $trend[$day] = [
                'learners' => isset($rows[$day]) ? (int)$rows[$day]->learners : 0,
                'hours' => isset($rows[$day]) ? round($rows[$day]->seconds / HOURSECS, 1) : 0,
            ];
        }
        return $trend;
    }

    /**
     * Learning hours by day of the week.
     *
     * @return float[] Hours, Monday first.
     */
    public function get_weekday_hours(): array {
        $hours = array_fill(0, 7, 0.0);
        $timezone = \core_date::get_server_timezone_object();
        foreach ($this->get_daily_trend() as $day => $figures) {
            $weekday = (int)(new \DateTime('@' . $day))->setTimezone($timezone)->format('N') - 1;
            $hours[$weekday] += $figures['hours'];
        }
        return array_map(fn($value) => round($value, 1), $hours);
    }

    /**
     * The courses with the most learning time in the period.
     *
     * @param int $limit
     * @return \stdClass[] id, fullname, hours, learners
     */
    public function get_top_courses(int $limit = 10): array {
        global $DB;
        [$scope, $params] = $this->scope('d.courseid', 'd.userid');
        $params += ['from' => $this->from, 'to' => $this->to, 'siteid' => SITEID];
        $rows = $DB->get_records_sql(
            "SELECT c.id, c.fullname, SUM(d.timespent) AS seconds, COUNT(DISTINCT d.userid) AS learners
               FROM {local_learnmetrics_daily} d
               JOIN {course} c ON c.id = d.courseid
              WHERE d.day >= :from AND d.day <= :to AND d.courseid <> :siteid $scope
           GROUP BY c.id, c.fullname
           ORDER BY SUM(d.timespent) DESC",
            $params,
            0,
            $limit
        );
        foreach ($rows as $row) {
            $row->fullname = format_string($row->fullname, true, ['context' => \context_course::instance($row->id)]);
            $row->hours = round($row->seconds / HOURSECS, 1);
        }
        return array_values($rows);
    }

    /**
     * Completion status of active enrolments: completed, in progress (some activity) and not started.
     *
     * @return int[] completed, inprogress, notstarted
     */
    public function get_completion_status(): array {
        global $DB;
        [$scope, $params] = $this->scope('e.courseid', 'ue.userid');
        $params += ['active' => ENROL_USER_ACTIVE, 'enabled' => ENROL_INSTANCE_ENABLED, 'siteid' => SITEID];
        $row = $DB->get_record_sql(
            "SELECT SUM(CASE WHEN cc.timecompleted > 0 THEN 1 ELSE 0 END) AS completed,
                    SUM(CASE WHEN (cc.timecompleted IS NULL OR cc.timecompleted = 0) AND lmc.timespent > 0 THEN 1 ELSE 0 END)
                        AS inprogress,
                    SUM(CASE WHEN (cc.timecompleted IS NULL OR cc.timecompleted = 0)
                              AND (lmc.timespent IS NULL OR lmc.timespent = 0) THEN 1 ELSE 0 END) AS notstarted
               FROM (SELECT DISTINCT ue.userid, e.courseid
                       FROM {user_enrolments} ue
                       JOIN {enrol} e ON e.id = ue.enrolid AND e.status = :enabled
                       JOIN {user} u ON u.id = ue.userid AND u.deleted = 0 AND u.suspended = 0
                      WHERE ue.status = :active AND e.courseid <> :siteid $scope) en
          LEFT JOIN {course_completions} cc ON cc.userid = en.userid AND cc.course = en.courseid
          LEFT JOIN {local_learnmetrics_course} lmc ON lmc.userid = en.userid AND lmc.courseid = en.courseid",
            $params
        );
        return [
            'completed' => (int)$row->completed,
            'inprogress' => (int)$row->inprogress,
            'notstarted' => (int)$row->notstarted,
        ];
    }

    /**
     * Learners who have not completed a course and have not been active in it for a while.
     *
     * A learner is inactive since their last activity in the course, or since their enrolment when they have never
     * been active in it.
     *
     * @param int $limit 0 for all.
     * @param int $inactivedays Inactive for at least this many days.
     * @param int $maxdays Only learners inactive for at most this many days; 0 for no limit.
     * @return \stdClass[] userid, courseid, coursename, fullname, lastactivity (0 for never), timespent
     */
    public function get_learners_needing_attention(
        int $limit = 20,
        int $inactivedays = self::INACTIVE_DAYS,
        int $maxdays = 0
    ): array {
        global $DB;
        [$scope, $params] = $this->scope('e.courseid', 'ue.userid');
        $params += [
            'active' => ENROL_USER_ACTIVE,
            'enabled' => ENROL_INSTANCE_ENABLED,
            'siteid' => SITEID,
            'since' => $this->to - $inactivedays * DAYSECS,
            'enrolledbefore' => $this->to - $inactivedays * DAYSECS,
        ];
        if ($maxdays) {
            $scope .= " AND (CASE WHEN COALESCE(lmc.lastactivity, 0) > ue.timecreated THEN lmc.lastactivity
                                  ELSE ue.timecreated END) >= :oldest";
            $params['oldest'] = $this->to - $maxdays * DAYSECS;
        }
        $userfields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
        // Across the site, hidden courses are left out; a course dashboard shows its course even when hidden.
        $visible = $this->courseid ? '' : 'AND c.visible = 1';
        $rows = $DB->get_records_sql(
            "SELECT DISTINCT " . $DB->sql_concat('u.id', "'-'", 'c.id') . " AS rowkey, u.id AS userid, c.id AS courseid,
                    c.fullname AS coursename, $userfields,
                    COALESCE(lmc.lastactivity, 0) AS lastactivity, COALESCE(lmc.timespent, 0) AS timespent
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid AND e.status = :enabled
               JOIN {course} c ON c.id = e.courseid
               JOIN {user} u ON u.id = ue.userid AND u.deleted = 0 AND u.suspended = 0
          LEFT JOIN {course_completions} cc ON cc.userid = u.id AND cc.course = c.id
          LEFT JOIN {local_learnmetrics_course} lmc ON lmc.userid = u.id AND lmc.courseid = c.id
              WHERE ue.status = :active AND c.id <> :siteid $visible
                AND (cc.timecompleted IS NULL OR cc.timecompleted = 0)
                AND COALESCE(lmc.lastactivity, 0) < :since
                AND ue.timecreated < :enrolledbefore $scope
           ORDER BY lastactivity ASC, userid ASC",
            $params,
            0,
            $limit
        );
        foreach ($rows as $row) {
            $row->fullname = fullname($row);
            $row->coursename = format_string($row->coursename, true, ['context' => \context_course::instance($row->courseid)]);
        }
        return array_values($rows);
    }

    /**
     * The learners with an active enrolment, in a course of the dashboard.
     *
     * @return int[] User ids; a learner enrolled in two courses of the dashboard once.
     */
    public function get_enrolled_learners(): array {
        global $DB;
        [$scope, $params] = $this->scope('e.courseid', 'ue.userid');
        $params += ['active' => ENROL_USER_ACTIVE, 'enabled' => ENROL_INSTANCE_ENABLED, 'siteid' => SITEID];
        return array_map('intval', $DB->get_fieldset_sql(
            "SELECT DISTINCT ue.userid
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid AND e.status = :enabled
               JOIN {user} u ON u.id = ue.userid AND u.deleted = 0 AND u.suspended = 0
              WHERE ue.status = :active AND e.courseid <> :siteid $scope",
            $params
        ));
    }

    /**
     * The learners behind a headline figure: one row per learner and course.
     *
     * The rows are selected exactly like the figure in {@see get_summary()}: active learners are learners with
     * learning time in the period (a learner active in two courses has two rows), enrolments and completions
     * are the enrolments created and the completions recorded in the period.
     *
     * @param string $metric One of {@see METRICS}.
     * @return array [sql, params]: rowkey, userid, courseid, coursename, the user name fields, timespent (in the
     *     period, in seconds), lastactivity (in the course, 0 for never), timecompleted (0 if not completed)
     */
    public function get_learners_sql(string $metric): array {
        global $DB;
        $params = ['from' => $this->from, 'to' => $this->to];
        switch ($metric) {
            case self::METRIC_ENROLMENTS:
                [$scope, $scopeparams] = $this->scope('e.courseid', 'ue.userid');
                $base = "SELECT ue.id AS rowkey, ue.userid, e.courseid
                           FROM {user_enrolments} ue
                           JOIN {enrol} e ON e.id = ue.enrolid
                          WHERE ue.timecreated >= :from AND ue.timecreated <= :to AND e.courseid <> :siteid $scope";
                $params['siteid'] = SITEID;
                break;
            case self::METRIC_COMPLETIONS:
                [$scope, $scopeparams] = $this->scope('cc.course', 'cc.userid');
                $base = "SELECT cc.id AS rowkey, cc.userid, cc.course AS courseid
                           FROM {course_completions} cc
                          WHERE cc.timecompleted >= :from AND cc.timecompleted <= :to $scope";
                break;
            case self::METRIC_ACTIVE:
                [$scope, $scopeparams] = $this->scope('d.courseid', 'd.userid');
                $base = "SELECT " . $DB->sql_concat('d.userid', "'-'", 'd.courseid') . " AS rowkey, d.userid, d.courseid
                           FROM {local_learnmetrics_daily} d
                          WHERE d.day >= :from AND d.day <= :to $scope
                       GROUP BY d.userid, d.courseid";
                break;
            default:
                throw new \coding_exception('Unknown metric: ' . $metric);
        }
        $params += $scopeparams + ['pfrom' => $this->from, 'pto' => $this->to];
        $userfields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
        $sql = "SELECT b.rowkey, b.userid, b.courseid, c.fullname AS coursename, $userfields,
                       COALESCE(pt.timespent, 0) AS timespent, COALESCE(lmc.lastactivity, 0) AS lastactivity,
                       COALESCE(cc.timecompleted, 0) AS timecompleted
                  FROM ($base) b
                  JOIN {user} u ON u.id = b.userid
                  JOIN {course} c ON c.id = b.courseid
             LEFT JOIN (SELECT pd.userid, pd.courseid, SUM(pd.timespent) AS timespent
                          FROM {local_learnmetrics_daily} pd
                         WHERE pd.day >= :pfrom AND pd.day <= :pto
                      GROUP BY pd.userid, pd.courseid) pt ON pt.userid = b.userid AND pt.courseid = b.courseid
             LEFT JOIN {local_learnmetrics_course} lmc ON lmc.userid = b.userid AND lmc.courseid = b.courseid
             LEFT JOIN {course_completions} cc ON cc.userid = b.userid AND cc.course = b.courseid";
        return [$sql, $params];
    }

    /**
     * The learners behind a headline figure, for tests and small lists.
     *
     * @param string $metric One of {@see METRICS}.
     * @return \stdClass[] As described in {@see get_learners_sql()}, by learner name.
     */
    public function get_learners(string $metric): array {
        global $DB;
        [$sql, $params] = $this->get_learners_sql($metric);
        return array_values($DB->get_records_sql(
            "SELECT * FROM ($sql) lm ORDER BY lm.lastname, lm.firstname, lm.rowkey",
            $params
        ));
    }
}
