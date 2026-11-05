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

/**
 * Upgrade steps of local_learnmetrics.
 *
 * @package    local_learnmetrics
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade local_learnmetrics.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_learnmetrics_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026102602) {
        // Still-here pings from course pages, until the time engine has processed them.
        $table = new xmldb_table('local_learnmetrics_ping');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('courseid', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);
        $table->add_index('userid-timecreated', XMLDB_INDEX_NOTUNIQUE, ['userid', 'timecreated']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, 2026102602, 'local', 'learnmetrics');
    }

    if ($oldversion < 2026102701) {
        // Whether the last action of a user came from a web browser, which sends pings.
        $table = new xmldb_table('local_learnmetrics_session');
        $field = new xmldb_field('web', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'timecreated');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        // Pauses without pings count only from now: before, pings may not have been sent, so the session gap applies.
        if (!get_config('local_learnmetrics', 'pingsince')) {
            set_config('pingsince', time(), 'local_learnmetrics');
        }
        upgrade_plugin_savepoint(true, 2026102701, 'local', 'learnmetrics');
    }

    if ($oldversion < 2026102800) {
        // Dashboard reports emailed as a PDF on a schedule.
        $table = new xmldb_table('local_learnmetrics_schedule');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('days', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '30');
        $table->add_field('categoryid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('cohortid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('frequency', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('weekday', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('monthday', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('hour', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '7');
        $table->add_field('userids', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('roleids', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('enabled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('nextrun', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('lastrun', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('usercreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('usercreated', XMLDB_KEY_FOREIGN, ['usercreated'], 'user', ['id']);
        $table->add_index('enabled-nextrun', XMLDB_INDEX_NOTUNIQUE, ['enabled', 'nextrun']);
        $table->add_index('courseid', XMLDB_INDEX_NOTUNIQUE, ['courseid']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, 2026102800, 'local', 'learnmetrics');
    }

    if ($oldversion < 2026102900) {
        // The last at-risk alert about a learner in a course, so it is sent once per period of inactivity.
        $table = new xmldb_table('local_learnmetrics_alert');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('lastactivity', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_key('courseid', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);
        $table->add_index('userid-courseid', XMLDB_INDEX_UNIQUE, ['userid', 'courseid']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, 2026102900, 'local', 'learnmetrics');
    }

    if ($oldversion < 2026103000) {
        // Compliance: courses that users must complete by a due date, and again when their completion expires.
        $table = new xmldb_table('local_learnmetrics_requirement');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('cohortids', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('duein', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('validfor', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('enabled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('usercreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('courseid', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);
        $table->add_key('usercreated', XMLDB_KEY_FOREIGN, ['usercreated'], 'user', ['id']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, 2026103000, 'local', 'learnmetrics');
    }

    if ($oldversion < 2026103100) {
        // Compliance reminders: off for existing requirements, so upgrading sends nothing until they are enabled.
        $table = new xmldb_table('local_learnmetrics_requirement');
        $field = new xmldb_field('reminddays', XMLDB_TYPE_INTEGER, '3', null, XMLDB_NOTNULL, null, '0', 'validfor');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // The reminders sent, so each is sent once per user, kind and due date.
        $table = new xmldb_table('local_learnmetrics_reminder');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('requirementid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('kind', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('duedate', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('requirementid', XMLDB_KEY_FOREIGN, ['requirementid'], 'local_learnmetrics_requirement', ['id']);
        $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_index('requirementid-userid-kind-duedate', XMLDB_INDEX_UNIQUE, ['requirementid', 'userid', 'kind', 'duedate']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, 2026103100, 'local', 'learnmetrics');
    }

    if ($oldversion < 2026110200) {
        // Scheduled reports: the department filter of the site dashboard.
        $table = new xmldb_table('local_learnmetrics_schedule');
        $field = new xmldb_field('department', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'cohortid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2026110200, 'local', 'learnmetrics');
    }

    if ($oldversion < 2026110300) {
        // The users of each compliance requirement and their dates, for Report builder.
        $table = new xmldb_table('local_learnmetrics_compuser');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('requirementid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('enrolled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timeassigned', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecompleted', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('duedate', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('expiry', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('requirementid', XMLDB_KEY_FOREIGN, ['requirementid'], 'local_learnmetrics_requirement', ['id']);
        $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_index('requirementid-userid', XMLDB_INDEX_UNIQUE, ['requirementid', 'userid']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        // Filled by the task "Refresh compliance for reports" within the hour.
        upgrade_plugin_savepoint(true, 2026110300, 'local', 'learnmetrics');
    }

    if ($oldversion < 2026110400) {
        // Learning time per activity, from now on: earlier time is not split by activity.
        $table = new xmldb_table('local_learnmetrics_cmtime');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('cmid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('day', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timespent', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('events', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_key('courseid', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);
        $table->add_key('cmid', XMLDB_KEY_FOREIGN, ['cmid'], 'course_modules', ['id']);
        $table->add_index('userid-cmid-day', XMLDB_INDEX_UNIQUE, ['userid', 'cmid', 'day']);
        $table->add_index('courseid-day', XMLDB_INDEX_NOTUNIQUE, ['courseid', 'day']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // The activity of each user's last action, so time after it counts towards the activity.
        $table = new xmldb_table('local_learnmetrics_session');
        $field = new xmldb_field('cmid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'web');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2026110400, 'local', 'learnmetrics');
    }

    if ($oldversion < 2026110700) {
        // Engagement scores, filled by the refresh_engagement task.
        $table = new xmldb_table('local_learnmetrics_engage');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('score', XMLDB_TYPE_INTEGER, '3', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('activedays', XMLDB_TYPE_INTEGER, '3', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timespent', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('progress', XMLDB_TYPE_INTEGER, '3', null, null, null, null);
        $table->add_field('lastactivity', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_key('courseid', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);
        $table->add_index('userid-courseid', XMLDB_INDEX_UNIQUE, ['userid', 'courseid']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, 2026110700, 'local', 'learnmetrics');
    }

    if ($oldversion < 2026111000) {
        // Scheduled reports, compliance, alerts, engagement and the other paid features moved to LearnMetrics Pro. Their
        // tables stay as they are, unused, until LearnMetrics Pro is installed and copies them. The permissions of the
        // moved capabilities are kept here for it, as Moodle removes them with the capabilities after this step.
        [$insql, $params] = $DB->get_in_or_equal(['local/learnmetrics:manageschedules', 'local/learnmetrics:managecompliance']);
        $permissions = $DB->get_records_select(
            'role_capabilities',
            "capability $insql",
            $params,
            'id',
            'id, contextid, roleid, capability, permission'
        );
        if ($permissions) {
            set_config('movedcapabilities', json_encode(array_values($permissions)), 'local_learnmetrics');
        }

        // The notification preferences of the moved messages, of users and the site defaults, are renamed for LearnMetrics
        // Pro, as Moodle deletes the preferences of messages a plugin no longer has after this step.
        foreach (['atrisklearner', 'atriskteacher', 'compliancereminder'] as $provider) {
            $old = "_provider_local_learnmetrics_{$provider}_";
            $new = "_provider_local_learnmetricspro_{$provider}_";
            $like = $DB->sql_like('name', ':name', false);
            foreach (['user_preferences' => [], 'config_plugins' => ['plugin' => 'message']] as $table => $conditions) {
                $select = $like . ($conditions ? ' AND plugin = :plugin' : '');
                $params = ['name' => '%' . $DB->sql_like_escape($old) . '%'] + $conditions;
                $rows = $DB->get_recordset_select($table, $select, $params, '', 'id, name');
                foreach ($rows as $row) {
                    $DB->set_field($table, 'name', str_replace($old, $new, $row->name), ['id' => $row->id]);
                }
                $rows->close();
            }
        }
        upgrade_plugin_savepoint(true, 2026111000, 'local', 'learnmetrics');
    }

    return true;
}
