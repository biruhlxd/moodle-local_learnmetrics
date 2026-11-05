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

use core_cohort\reportbuilder\local\entities\cohort;
use core_course\reportbuilder\local\entities\access;
use core_course\reportbuilder\local\entities\completion;
use core_course\reportbuilder\local\entities\course_category;
use core_course\reportbuilder\local\entities\enrolment;
use core_enrol\reportbuilder\local\entities\enrol;
use core_reportbuilder\datasource;
use core_reportbuilder\local\entities\course;
use core_reportbuilder\local\entities\user;
use core_reportbuilder\local\filters\select;
use core_reportbuilder\local\helpers\database;
use core_user\output\status_field;
use local_learnmetrics\hook\learner_activity_source;
use local_learnmetrics\reportbuilder\local\entities\learning_time;

/**
 * Learner activity: each learner in each course, with enrolment, completion, grade and learning time.
 *
 * Built like the core "Course participants" source, with the learning time recorded by this plugin. Unlike that
 * source, every row is an enrolled user: enrolment methods nobody is enrolled with are left out.
 *
 * @package    local_learnmetrics
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class learner_activity extends datasource {
    /**
     * Initialise the report source.
     */
    protected function initialise(): void {
        $courseentity = new course();
        $course = $courseentity->get_table_alias('course');
        $this->add_entity($courseentity);
        $this->set_main_table('course', $course);

        // Not the site course.
        $paramsiteid = database::generate_param_name();
        $this->add_base_condition_sql("{$course}.id != :{$paramsiteid}", [$paramsiteid => SITEID]);

        $categoryentity = new course_category();
        $categories = $categoryentity->get_table_alias('course_categories');
        $this->add_entity($categoryentity
            ->add_join("LEFT JOIN {course_categories} {$categories} ON {$categories}.id = {$course}.category"));

        $enrolentity = new enrol();
        $enrol = $enrolentity->get_table_alias('enrol');
        $this->add_entity($enrolentity
            ->add_join("JOIN {enrol} {$enrol} ON {$enrol}.courseid = {$course}.id"));

        $enrolmententity = (new enrolment())->set_table_alias('enrol', $enrol);
        $userenrolment = $enrolmententity->get_table_alias('user_enrolments');
        $this->add_entity($enrolmententity
            ->add_joins($enrolentity->get_joins())
            ->add_join("JOIN {user_enrolments} {$userenrolment} ON {$userenrolment}.enrolid = {$enrol}.id"));

        $userentity = new user();
        $user = $userentity->get_table_alias('user');
        $this->add_entity($userentity
            ->add_joins($enrolmententity->get_joins())
            ->add_join("JOIN {user} {$user} ON {$user}.id = {$userenrolment}.userid AND {$user}.deleted = 0"));

        $cohortentity = new cohort();
        $cohortalias = $cohortentity->get_table_alias('cohort');
        $cohortmember = database::generate_alias();
        $this->add_entity($cohortentity
            ->add_joins($userentity->get_joins())
            ->add_joins([
                "LEFT JOIN {cohort_members} {$cohortmember} ON {$cohortmember}.userid = {$user}.id",
                "LEFT JOIN {cohort} {$cohortalias} ON {$cohortalias}.id = {$cohortmember}.cohortid",
            ]));

        $completionentity = (new completion())->set_table_aliases(['course' => $course, 'user' => $user]);
        $completion = $completionentity->get_table_alias('course_completions');
        $this->add_entity($completionentity
            ->add_joins($userentity->get_joins())
            ->add_join("LEFT JOIN {course_completions} {$completion}
                               ON {$completion}.course = {$course}.id AND {$completion}.userid = {$user}.id"));

        $accessentity = (new access())->set_table_alias('user', $user);
        $lastaccess = $accessentity->get_table_alias('user_lastaccess');
        $this->add_entity($accessentity
            ->add_joins($userentity->get_joins())
            ->add_join("LEFT JOIN {user_lastaccess} {$lastaccess}
                               ON {$lastaccess}.userid = {$user}.id AND {$lastaccess}.courseid = {$course}.id"));

        $timeentity = new learning_time();
        $time = $timeentity->get_table_alias('local_learnmetrics_course');
        $this->add_entity($timeentity
            ->add_joins($userentity->get_joins())
            ->add_join("LEFT JOIN {local_learnmetrics_course} {$time}
                               ON {$time}.userid = {$user}.id AND {$time}.courseid = {$course}.id"));

        // Entities of other plugins.
        $extras = new learner_activity_source($course, $user, $userentity->get_joins());
        \core\di::get(\core\hook\manager::class)->dispatch($extras);
        foreach ($extras->get_entities() as $entity) {
            $this->add_entity($entity);
        }

        // By entity name: Moodle 5.0 and 5.1 do not take entity objects here.
        $this->add_all_from_entities(array_map(
            fn($entity) => $entity->get_entity_name(),
            [$categoryentity, $courseentity, $enrolentity, $enrolmententity, $userentity]
        ));
        $this->add_all_from_entity(
            $cohortentity->get_entity_name(),
            ['name', 'idnumber'],
            ['cohortselect', 'name', 'idnumber'],
            ['cohortselect', 'name', 'idnumber'],
        );
        $this->add_all_from_entities(array_map(
            fn($entity) => $entity->get_entity_name(),
            array_merge([$completionentity, $accessentity, $timeentity], $extras->get_entities())
        ));
    }

    /**
     * Name of the report source.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('learneractivity', 'local_learnmetrics');
    }

    /**
     * Columns of a new report.
     *
     * @return string[]
     */
    public function get_default_columns(): array {
        return [
            'course:coursefullnamewithlink',
            'user:fullnamewithlink',
            'completion:progresspercent',
            'learning_time:timespent',
            'learning_time:activedays',
            'learning_time:lastactivity',
        ];
    }

    /**
     * Sorting of a new report.
     *
     * @return int[]
     */
    public function get_default_column_sorting(): array {
        return [
            'course:coursefullnamewithlink' => SORT_ASC,
            'user:fullnamewithlink' => SORT_ASC,
        ];
    }

    /**
     * Filters of a new report.
     *
     * @return string[]
     */
    public function get_default_filters(): array {
        return ['course:fullname', 'cohort:cohortselect', 'learning_time:lastactivity'];
    }

    /**
     * Conditions of a new report.
     *
     * @return string[]
     */
    public function get_default_conditions(): array {
        return ['enrolment:status', 'user:suspended'];
    }

    /**
     * Condition values of a new report: active enrolments of active users.
     *
     * @return array
     */
    public function get_default_condition_values(): array {
        return [
            'enrolment:status_operator' => select::EQUAL_TO,
            'enrolment:status_value' => status_field::STATUS_ACTIVE,
        ];
    }
}
