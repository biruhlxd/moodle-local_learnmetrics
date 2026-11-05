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

use core_course\reportbuilder\local\entities\course_category;
use core_reportbuilder\datasource;
use core_reportbuilder\local\entities\course;
use core_reportbuilder\local\entities\user;
use local_learnmetrics\reportbuilder\local\entities\scorm_attempt;

/**
 * SCORM attempts: one row per learner and attempt at a SCORM activity, with status, score, time and access.
 *
 * Every row is a real attempt of an existing user: the tables are joined with inner joins.
 *
 * @package    local_learnmetrics
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class scorm_attempts extends datasource {
    /**
     * Initialise the report source.
     */
    protected function initialise(): void {
        $attemptentity = new scorm_attempt();
        $attempt = $attemptentity->get_table_alias('scorm_attempt');
        $scorm = $attemptentity->get_table_alias('scorm');
        $this->set_main_table('scorm_attempt', $attempt);
        $this->add_entity($attemptentity
            ->add_join("JOIN {scorm} {$scorm} ON {$scorm}.id = {$attempt}.scormid"));

        $courseentity = new course();
        $course = $courseentity->get_table_alias('course');
        $this->add_entity($courseentity
            ->add_joins($attemptentity->get_joins())
            ->add_join("JOIN {course} {$course} ON {$course}.id = {$scorm}.course"));

        $categoryentity = new course_category();
        $categories = $categoryentity->get_table_alias('course_categories');
        $this->add_entity($categoryentity
            ->add_joins($courseentity->get_joins())
            ->add_join("JOIN {course_categories} {$categories} ON {$categories}.id = {$course}.category"));

        $userentity = new user();
        $user = $userentity->get_table_alias('user');
        $this->add_entity($userentity
            ->add_join("JOIN {user} {$user} ON {$user}.id = {$attempt}.userid AND {$user}.deleted = 0"));

        // By entity name: Moodle 5.0 and 5.1 do not take entity objects here.
        $this->add_all_from_entities(array_map(
            fn($entity) => $entity->get_entity_name(),
            [$courseentity, $categoryentity, $attemptentity, $userentity]
        ));
    }

    /**
     * Name of the report source.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('scormattempts', 'local_learnmetrics');
    }

    /**
     * Columns of a new report.
     *
     * @return string[]
     */
    public function get_default_columns(): array {
        return [
            'course:coursefullnamewithlink',
            'scorm_attempt:namewithlink',
            'user:fullnamewithlink',
            'scorm_attempt:attempt',
            'scorm_attempt:status',
            'scorm_attempt:scoreraw',
            'scorm_attempt:totaltime',
            'scorm_attempt:lastaccess',
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
            'scorm_attempt:namewithlink' => SORT_ASC,
            'user:fullnamewithlink' => SORT_ASC,
            'scorm_attempt:attempt' => SORT_ASC,
        ];
    }

    /**
     * Filters of a new report.
     *
     * @return string[]
     */
    public function get_default_filters(): array {
        return ['course:fullname', 'scorm_attempt:name', 'scorm_attempt:status', 'user:fullname'];
    }

    /**
     * Conditions of a new report.
     *
     * @return string[]
     */
    public function get_default_conditions(): array {
        return ['course_category:name', 'course:fullname', 'scorm_attempt:name', 'user:suspended'];
    }
}
