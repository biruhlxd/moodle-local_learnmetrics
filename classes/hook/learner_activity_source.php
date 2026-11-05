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

namespace local_learnmetrics\hook;

use core_reportbuilder\local\entities\base;

/**
 * Lets plugins add entities to the Learner activity report source (and the sources built on it): each row is a
 * learner in a course.
 *
 * An entity joins its tables to the course and user of the row, after the user's joins, for example:
 * <code>
 * $entity->add_joins($hook->get_user_joins())
 *     ->add_join("LEFT JOIN {my_table} t ON t.userid = {$hook->get_user_alias()}.id
 *                                     AND t.courseid = {$hook->get_course_alias()}.id");
 * $hook->add_entity($entity);
 * </code>
 *
 * @package    local_learnmetrics
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\core\attribute\label('Allows plugins to add entities to the Learner activity report source.')]
#[\core\attribute\tags('local_learnmetrics', 'reportbuilder')]
final class learner_activity_source {
    /** @var base[] Entities to add, with all their columns, filters and conditions. */
    protected $entities = [];

    /**
     * Constructor.
     *
     * @param string $course Alias of the course table.
     * @param string $user Alias of the user table.
     * @param string[] $userjoins Joins that reach the user table.
     */
    public function __construct(
        /** @var string Alias of the course table. */
        protected readonly string $course,
        /** @var string Alias of the user table. */
        protected readonly string $user,
        /** @var string[] Joins that reach the user table. */
        protected readonly array $userjoins
    ) {
    }

    /**
     * Alias of the course table.
     *
     * @return string
     */
    public function get_course_alias(): string {
        return $this->course;
    }

    /**
     * Alias of the user table.
     *
     * @return string
     */
    public function get_user_alias(): string {
        return $this->user;
    }

    /**
     * Joins that reach the user table, for the entity to add before its own.
     *
     * @return string[]
     */
    public function get_user_joins(): array {
        return $this->userjoins;
    }

    /**
     * Add an entity, with its joins.
     *
     * @param base $entity
     */
    public function add_entity(base $entity): void {
        $this->entities[] = $entity;
    }

    /**
     * The added entities.
     *
     * @return base[]
     */
    public function get_entities(): array {
        return $this->entities;
    }
}
