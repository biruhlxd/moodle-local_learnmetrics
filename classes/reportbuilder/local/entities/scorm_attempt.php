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
use core_reportbuilder\local\filters\number;
use core_reportbuilder\local\filters\select;
use core_reportbuilder\local\filters\text;
use core_reportbuilder\local\helpers\format;
use core_reportbuilder\local\report\column;
use core_reportbuilder\local\report\filter;
use html_writer;
use lang_string;
use moodle_url;
use stdClass;

/**
 * An attempt of a learner at a SCORM activity: activity, attempt number, status, score, time and access.
 *
 * Read from the tracks that mod_scorm stores ({scorm_attempt}, {scorm_scoes_value}, {scorm_element}), for SCORM 1.2
 * and SCORM 2004. A package with several SCOs gives one row per attempt: the best status of its SCOs (a failed
 * test outranks a completed one), the highest score and the total time of all SCOs.
 *
 * The datasource joins {scorm_attempt} and {scorm} using this entity's table aliases.
 *
 * @package    local_learnmetrics
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class scorm_attempt extends base {
    /** @var int No status recorded. */
    const STATUS_NOTATTEMPTED = 1;

    /** @var int Browsed. */
    const STATUS_BROWSED = 2;

    /** @var int Incomplete. */
    const STATUS_INCOMPLETE = 3;

    /** @var int Completed. */
    const STATUS_COMPLETED = 4;

    /** @var int Failed. */
    const STATUS_FAILED = 5;

    /** @var int Passed. */
    const STATUS_PASSED = 6;

    /** @var string[] Status values of SCORM 1.2 and 2004, by status. */
    const STATUS_VALUES = [
        self::STATUS_NOTATTEMPTED => 'not attempted',
        self::STATUS_BROWSED => 'browsed',
        self::STATUS_INCOMPLETE => 'incomplete',
        self::STATUS_COMPLETED => 'completed',
        self::STATUS_FAILED => 'failed',
        self::STATUS_PASSED => 'passed',
    ];

    /** @var string[] Elements with the status: SCORM 1.2 lesson status, SCORM 2004 completion and success status. */
    const STATUS_ELEMENTS = ['cmi.core.lesson_status', 'cmi.completion_status', 'cmi.success_status'];

    /** @var string[] Elements with the raw score, SCORM 1.2 and 2004. */
    const SCORE_ELEMENTS = ['cmi.core.score.raw', 'cmi.score.raw'];

    /** @var string[] Elements with the total time, SCORM 1.2 and 2004. */
    const TIME_ELEMENTS = ['cmi.core.total_time', 'cmi.total_time'];

    /**
     * Database tables that this entity uses.
     *
     * @return string[]
     */
    protected function get_default_tables(): array {
        return ['scorm_attempt', 'scorm', 'course_modules', 'modules', 'scorm_scoes_value', 'scorm_element'];
    }

    /**
     * The default title for this entity.
     *
     * @return lang_string
     */
    protected function get_default_entity_title(): lang_string {
        return new lang_string('scormattempt', 'local_learnmetrics');
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
     * SQL of a value of the attempt, from the SCO tracks.
     *
     * @param string $aggregate SQL aggregate of the value, using v.value (and v.timemodified).
     * @param string[] $elements Only these elements; all if empty.
     * @param string $where More conditions.
     * @return string A subquery.
     */
    protected function track_sql(string $aggregate, array $elements = [], string $where = ''): string {
        $attempt = $this->get_table_alias('scorm_attempt');
        $value = $this->get_table_alias('scorm_scoes_value');
        $element = $this->get_table_alias('scorm_element');
        $aggregate = str_replace('v.', "{$value}.", $aggregate);
        $where = str_replace('v.', "{$value}.", $where);
        // The element names are constants of this class, never user input.
        $in = $elements ? "AND {$element}.element IN ('" . implode("', '", $elements) . "')" : '';
        return "(SELECT {$aggregate}
                   FROM {scorm_scoes_value} {$value}
                   JOIN {scorm_element} {$element} ON {$element}.id = {$value}.elementid
                  WHERE {$value}.attemptid = {$attempt}.id {$in} {$where})";
    }

    /**
     * SQL of the status of the attempt, one of the STATUS_ constants.
     *
     * @return string
     */
    protected function status_sql(): string {
        global $DB;
        $value = $DB->sql_compare_text('v.value', 20);
        $cases = '';
        foreach (self::STATUS_VALUES as $status => $text) {
            $cases .= " WHEN {$value} = '{$text}' THEN {$status}";
        }
        return 'COALESCE(' . $this->track_sql("MAX(CASE {$cases} END)", self::STATUS_ELEMENTS) . ', '
            . self::STATUS_NOTATTEMPTED . ')';
    }

    /**
     * SQL of the highest raw score of the attempt.
     *
     * @return string
     */
    protected function score_sql(): string {
        global $DB;
        return $this->track_sql(
            'MAX(' . $DB->sql_cast_char2real('v.value', true) . ')',
            self::SCORE_ELEMENTS,
            "AND " . $DB->sql_compare_text('v.value', 30) . " <> ''"
        );
    }

    /**
     * Columns.
     *
     * @return column[]
     */
    protected function get_available_columns(): array {
        global $DB;
        $attempt = $this->get_table_alias('scorm_attempt');
        $scorm = $this->get_table_alias('scorm');
        $cm = $this->get_table_alias('course_modules');
        $module = $this->get_table_alias('modules');
        $columns = [];

        $columns[] = (new column('name', new lang_string('scormname', 'local_learnmetrics'), $this->get_entity_name()))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_fields("{$scorm}.name, {$scorm}.course")
            ->set_is_sortable(true)
            ->add_callback(static function (?string $value, stdClass $row): string {
                if ($value === null) {
                    return '';
                }
                return format_string($value, true, ['context' => \context_course::instance($row->course)]);
            });

        $columns[] = (new column(
            'namewithlink',
            new lang_string('scormnamewithlink', 'local_learnmetrics'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_fields("{$scorm}.name, {$scorm}.course")
            ->add_field("(SELECT {$cm}.id
                            FROM {course_modules} {$cm}
                            JOIN {modules} {$module} ON {$module}.id = {$cm}.module AND {$module}.name = 'scorm'
                           WHERE {$cm}.instance = {$scorm}.id)", 'cmid')
            ->set_is_sortable(true, ["{$scorm}.name"])
            ->add_callback(static function (?string $value, stdClass $row): string {
                if ($value === null) {
                    return '';
                }
                $name = format_string($value, true, ['context' => \context_course::instance($row->course)]);
                if (!$row->cmid) {
                    return $name;
                }
                return html_writer::link(new moodle_url('/mod/scorm/view.php', ['id' => $row->cmid]), $name);
            });

        $columns[] = (new column('attempt', new lang_string('attemptnumber', 'local_learnmetrics'), $this->get_entity_name()))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_INTEGER)
            ->add_field("{$attempt}.attempt")
            ->set_is_sortable(true);

        $columns[] = (new column('status', new lang_string('lessonstatus', 'local_learnmetrics'), $this->get_entity_name()))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_INTEGER)
            ->add_field($this->status_sql(), 'status')
            ->set_is_sortable(true)
            ->set_disabled_aggregation_all()
            ->add_callback(static function ($value): string {
                return $value === null ? '' : self::get_status_options()[(int)$value] ?? '';
            });

        $columns[] = (new column('scoreraw', new lang_string('scoreraw', 'local_learnmetrics'), $this->get_entity_name()))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_FLOAT)
            ->add_field($this->score_sql(), 'scoreraw')
            ->set_is_sortable(true)
            ->add_callback(static function ($value): string {
                return $value === null ? '-' : format_float((float)$value, 2, true, true);
            });

        $columns[] = (new column('totaltime', new lang_string('totaltime', 'local_learnmetrics'), $this->get_entity_name()))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field($this->track_sql($DB->sql_group_concat('v.value', ','), self::TIME_ELEMENTS), 'totaltime')
            ->set_disabled_aggregation_all()
            ->add_callback(static function (?string $value): string {
                $seconds = self::parse_total_time((string)$value);
                return $seconds ? format_time($seconds) : '-';
            });

        foreach (['firstaccess' => 'MIN', 'lastaccess' => 'MAX'] as $name => $aggregate) {
            $columns[] = (new column($name, new lang_string($name, 'local_learnmetrics'), $this->get_entity_name()))
                ->add_joins($this->get_joins())
                ->set_type(column::TYPE_TIMESTAMP)
                ->add_field($this->track_sql("{$aggregate}(v.timemodified)"), $name)
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
        $attempt = $this->get_table_alias('scorm_attempt');
        $scorm = $this->get_table_alias('scorm');
        return [
            (new filter(
                text::class,
                'name',
                new lang_string('scormname', 'local_learnmetrics'),
                $this->get_entity_name(),
                "{$scorm}.name"
            ))
                ->add_joins($this->get_joins()),
            (new filter(
                number::class,
                'attempt',
                new lang_string('attemptnumber', 'local_learnmetrics'),
                $this->get_entity_name(),
                "{$attempt}.attempt"
            ))
                ->add_joins($this->get_joins()),
            (new filter(
                select::class,
                'status',
                new lang_string('lessonstatus', 'local_learnmetrics'),
                $this->get_entity_name(),
                $this->status_sql()
            ))
                ->add_joins($this->get_joins())
                ->set_options_callback([self::class, 'get_status_options']),
            (new filter(
                number::class,
                'scoreraw',
                new lang_string('scoreraw', 'local_learnmetrics'),
                $this->get_entity_name(),
                $this->score_sql()
            ))
                ->add_joins($this->get_joins()),
            (new filter(
                date::class,
                'lastaccess',
                new lang_string('lastaccess', 'local_learnmetrics'),
                $this->get_entity_name(),
                $this->track_sql('MAX(v.timemodified)')
            ))
                ->add_joins($this->get_joins())
                ->set_limited_operators([date::DATE_ANY, date::DATE_NOT_EMPTY, date::DATE_EMPTY, date::DATE_RANGE,
                    date::DATE_LAST, date::DATE_CURRENT]),
        ];
    }

    /**
     * Names of the statuses.
     *
     * @return string[] By status.
     */
    public static function get_status_options(): array {
        return [
            self::STATUS_NOTATTEMPTED => get_string('notattempted', 'scorm'),
            self::STATUS_BROWSED => get_string('browsed', 'scorm'),
            self::STATUS_INCOMPLETE => get_string('incomplete', 'scorm'),
            self::STATUS_COMPLETED => get_string('completed', 'scorm'),
            self::STATUS_FAILED => get_string('failed', 'scorm'),
            self::STATUS_PASSED => get_string('passed', 'scorm'),
        ];
    }

    /**
     * Add up SCORM time values: SCORM 1.2 (HHHH:MM:SS.SS) and SCORM 2004 (ISO 8601 duration, such as PT1H5M30S).
     *
     * @param string $values Values separated by commas.
     * @return int Seconds.
     */
    public static function parse_total_time(string $values): int {
        $total = 0.0;
        foreach (explode(',', $values) as $value) {
            $value = trim($value);
            if (preg_match('/^(\d+):(\d{1,2}):(\d{1,2}(?:\.\d+)?)$/', $value, $m)) {
                $total += $m[1] * 3600 + $m[2] * 60 + (float)$m[3];
            } else if (
                preg_match(
                    '/^P(?:(\d+)Y)?(?:(\d+)M)?(?:(\d+)D)?(?:T(?:(\d+)H)?(?:(\d+)M)?(?:(\d+(?:\.\d+)?)S)?)?$/',
                    $value,
                    $m
                ) && $value !== 'P' && $value !== 'PT'
            ) {
                $m = array_pad($m, 7, 0);
                $total += (float)$m[1] * 365 * DAYSECS + (float)$m[2] * 30 * DAYSECS + (float)$m[3] * DAYSECS
                    + (float)$m[4] * HOURSECS + (float)$m[5] * MINSECS + (float)$m[6];
            }
        }
        return (int)round($total);
    }
}
