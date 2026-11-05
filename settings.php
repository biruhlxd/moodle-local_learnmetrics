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
 * Admin settings and pages of local_learnmetrics.
 *
 * @package    local_learnmetrics
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$ADMIN->add('reports', new admin_externalpage(
    'local_learnmetrics_dashboard',
    get_string('dashboard', 'local_learnmetrics'),
    new moodle_url('/local/learnmetrics/index.php'),
    'local/learnmetrics:viewdashboard'
));

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_learnmetrics', get_string('pluginname', 'local_learnmetrics'));
    $ADMIN->add('localplugins', $settings);
    if ($ADMIN->fulltree) {
        $settings->add(new admin_setting_configduration(
            'local_learnmetrics/sessiongap',
            get_string('sessiongap', 'local_learnmetrics'),
            get_string('sessiongap_desc', 'local_learnmetrics'),
            \local_learnmetrics\local\time_tracker::DEFAULT_SESSION_GAP,
            MINSECS
        ));
        $settings->add(new admin_setting_configtext(
            'local_learnmetrics/backfilldays',
            get_string('backfilldays', 'local_learnmetrics'),
            get_string('backfilldays_desc', 'local_learnmetrics'),
            \local_learnmetrics\local\time_tracker::DEFAULT_BACKFILL_DAYS,
            PARAM_INT
        ));
        $settings->add(new admin_setting_configcheckbox(
            'local_learnmetrics/heartbeat',
            get_string('heartbeat', 'local_learnmetrics'),
            get_string('heartbeat_desc', 'local_learnmetrics'),
            1
        ));
        $settings->add(new admin_setting_pickroles(
            'local_learnmetrics/learnerroles',
            get_string('learnerroles', 'local_learnmetrics'),
            get_string('learnerroles_desc', 'local_learnmetrics'),
            ['student']
        ));
        $settings->add(new admin_setting_configcheckbox(
            'local_learnmetrics/includeadmins',
            get_string('includeadmins', 'local_learnmetrics'),
            get_string('includeadmins_desc', 'local_learnmetrics'),
            0
        ));
        $settings->add(new admin_setting_configselect(
            'local_learnmetrics/departmentfield',
            get_string('departmentfield', 'local_learnmetrics'),
            get_string('departmentfield_desc', 'local_learnmetrics'),
            'department',
            \local_learnmetrics\local\department::get_field_options()
        ));
    }
}
