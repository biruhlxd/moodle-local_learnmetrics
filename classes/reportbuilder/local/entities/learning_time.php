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

namespace local_learnmetrics\reportbuilder\local\entities;

use core_reportbuilder\local\entities\base;
use core_reportbuilder\local\filters\date;
use core_reportbuilder\local\filters\duration;
use core_reportbuilder\local\filters\number;
use core_reportbuilder\local\helpers\format;
use core_reportbuilder\local\report\column;
use core_reportbuilder\local\report\filter;
use lang_string;
use stdClass;

/**
 * Learning time of a user in a course: time spent, actions, active days, first and last activity.
 *
 * The datasource joins {local_learnmetrics_course} using this entity's table alias.
 *
 * @package    local_learnmetrics
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class learning_time extends base {
    /**
     * Database tables that this entity uses.
     *
     * @return string[]
     */
    protected function get_default_tables(): array {
        return ['local_learnmetrics_course'];
    }

    /**
     * The default title for this entity.
     *
     * @return lang_string
     */
    protected function get_default_entity_title(): lang_string {
        return new lang_string('learningtime', 'local_learnmetrics');
    }

    /**
     * Initialise the entity.
     *
     * @return base
     */
    public function initialise(): base {
        foreach ($this->get_available_columns() as $column) {
            $this->add_column($column);
        }
        foreach ($this->get_available_filters() as $filter) {
            $this->add_filter($filter);
            $this->add_condition($filter);
        }
        return $this;
    }

    /**
     * Columns.
     *
     * @return column[]
     */
    protected function get_available_columns(): array {
        $alias = $this->get_table_alias('local_learnmetrics_course');
        $columns = [];

        $columns[] = (new column('timespent', new lang_string('timespent', 'local_learnmetrics'), $this->get_entity_name()))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_INTEGER)
            ->add_field("COALESCE({$alias}.timespent, 0)", 'timespent')
            ->set_is_sortable(true)
            ->add_callback(static function ($value): string {
                return $value ? format_time((int)$value) : '-';
            });

        $columns[] = (new column('hours', new lang_string('hoursspent', 'local_learnmetrics'), $this->get_entity_name()))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_FLOAT)
            ->add_field("COALESCE({$alias}.timespent, 0) / 3600.0", 'hours')
            ->set_is_sortable(true)
            ->add_callback(static function ($value): string {
                return format_float((float)$value, 1);
            });

        $columns[] = (new column('events', new lang_string('actions', 'local_learnmetrics'), $this->get_entity_name()))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_INTEGER)
            ->add_field("COALESCE({$alias}.events, 0)", 'events')
            ->set_is_sortable(true);

        $columns[] = (new column('activedays', new lang_string('activedays', 'local_learnmetrics'), $this->get_entity_name()))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_INTEGER)
            ->add_field("COALESCE({$alias}.activedays, 0)", 'activedays')
            ->set_is_sortable(true);

        foreach (['firstactivity', 'lastactivity'] as $name) {
            $columns[] = (new column($name, new lang_string($name, 'local_learnmetrics'), $this->get_entity_name()))
                ->add_joins($this->get_joins())
                ->set_type(column::TYPE_TIMESTAMP)
                ->add_field("{$alias}.{$name}")
                ->set_is_sortable(true)
                ->add_callback(static function (?int $value, stdClass $row): string {
                    return $value ? format::userdate($value, $row) : get_string('never');
                });
        }
        return $columns;
    }

    /**
     * Filters, also used as conditions.
     *
     * @return filter[]
     */
    protected function get_available_filters(): array {
        $alias = $this->get_table_alias('local_learnmetrics_course');
        return [
            (new filter(
                duration::class,
                'timespent',
                new lang_string('timespent', 'local_learnmetrics'),
                $this->get_entity_name(),
                "COALESCE({$alias}.timespent, 0)"
            ))
                ->add_joins($this->get_joins()),
            (new filter(
                number::class,
                'activedays',
                new lang_string('activedays', 'local_learnmetrics'),
                $this->get_entity_name(),
                "COALESCE({$alias}.activedays, 0)"
            ))
                ->add_joins($this->get_joins()),
            (new filter(
                date::class,
                'lastactivity',
                new lang_string('lastactivity', 'local_learnmetrics'),
                $this->get_entity_name(),
                "{$alias}.lastactivity"
            ))
                ->add_joins($this->get_joins())
                ->set_limited_operators([date::DATE_ANY, date::DATE_NOT_EMPTY, date::DATE_EMPTY, date::DATE_RANGE,
                    date::DATE_LAST, date::DATE_CURRENT]),
        ];
    }
}
