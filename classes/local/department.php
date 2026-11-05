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
 * The department filter: learners grouped by a field of their profile, such as Department or a custom field.
 *
 * @package    local_learnmetrics
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class department {
    /** @var string[] Fields of the user table that can be used. */
    const USER_FIELDS = ['department', 'institution', 'city'];

    /** @var string Prefix of custom profile fields in the setting. */
    const PROFILE_PREFIX = 'profile_field_';

    /** @var string[] Types of custom profile fields that can be used: one short value per user. */
    const PROFILE_TYPES = ['text', 'menu'];

    /** @var int At most this many values are offered in the filter. */
    const MAX_VALUES = 1000;

    /**
     * The fields that can be used, for the setting.
     *
     * @return string[] Setting value => name; '' to turn the filter off.
     */
    public static function get_field_options(): array {
        global $DB;
        $options = ['' => get_string('none')];
        foreach (self::USER_FIELDS as $field) {
            $options[$field] = get_string($field);
        }
        [$insql, $params] = $DB->get_in_or_equal(self::PROFILE_TYPES, SQL_PARAMS_NAMED);
        $fields = $DB->get_records_select('user_info_field', "datatype $insql", $params, 'sortorder', 'id, shortname, name');
        $context = \context_system::instance();
        foreach ($fields as $field) {
            $options[self::PROFILE_PREFIX . $field->shortname] = format_string($field->name, true, ['context' => $context]);
        }
        return $options;
    }

    /**
     * The field chosen in the settings.
     *
     * @return \stdClass|null name (shown on the dashboards), and either userfield (a column of the user table) or
     *     profilefieldid; null when the filter is off or its custom field no longer exists.
     */
    public static function get_field(): ?\stdClass {
        global $DB;
        $setting = get_config('local_learnmetrics', 'departmentfield');
        $setting = $setting === false ? 'department' : (string)$setting;
        if (in_array($setting, self::USER_FIELDS)) {
            return (object)['name' => get_string($setting), 'userfield' => $setting, 'profilefieldid' => 0];
        }
        if (str_starts_with($setting, self::PROFILE_PREFIX)) {
            $field = $DB->get_record('user_info_field', ['shortname' => substr($setting, strlen(self::PROFILE_PREFIX))]);
            if ($field && in_array($field->datatype, self::PROFILE_TYPES)) {
                $name = format_string($field->name, true, ['context' => \context_system::instance()]);
                return (object)['name' => $name, 'userfield' => '', 'profilefieldid' => (int)$field->id];
            }
        }
        return null;
    }

    /**
     * The values of the field among active users, for the filter.
     *
     * @return string[] Sorted; empty when the filter is off or nobody has a value.
     */
    public static function get_values(): array {
        global $DB;
        $field = self::get_field();
        if (!$field) {
            return [];
        }
        if ($field->userfield) {
            $values = $DB->get_fieldset_sql(
                "SELECT DISTINCT u.{$field->userfield}
                   FROM {user} u
                  WHERE u.deleted = 0 AND u.suspended = 0 AND u.{$field->userfield} <> ''",
                [],
            );
        } else {
            $data = $DB->sql_compare_text('d.data', 255);
            $values = $DB->get_fieldset_sql(
                "SELECT DISTINCT $data
                   FROM {user_info_data} d
                   JOIN {user} u ON u.id = d.userid AND u.deleted = 0 AND u.suspended = 0
                  WHERE d.fieldid = :fieldid AND $data <> ''",
                ['fieldid' => $field->profilefieldid],
            );
        }
        $values = array_unique(array_filter($values, fn($value) => trim($value) !== ''));
        \core_collator::asort($values);
        return array_slice(array_values($values), 0, self::MAX_VALUES);
    }

    /**
     * SQL that limits rows to the users with a value of the field.
     *
     * @param string $userid Column with the user id.
     * @param string $value
     * @return array [sql starting with AND; params]; nobody when the filter is off.
     */
    public static function sql(string $userid, string $value): array {
        global $DB;
        $field = self::get_field();
        if (!$field) {
            return [' AND 1 = 0', []];
        }
        if ($field->userfield) {
            return [" AND EXISTS (SELECT 1 FROM {user} lmsdu WHERE lmsdu.id = $userid
                                     AND lmsdu.{$field->userfield} = :lmdepartment)", ['lmdepartment' => $value]];
        }
        $data = $DB->sql_compare_text('lmsdd.data', 255);
        return [" AND EXISTS (SELECT 1 FROM {user_info_data} lmsdd WHERE lmsdd.userid = $userid
                                 AND lmsdd.fieldid = :lmdepartmentfield AND $data = :lmdepartment)",
            ['lmdepartmentfield' => $field->profilefieldid, 'lmdepartment' => $value]];
    }
}
