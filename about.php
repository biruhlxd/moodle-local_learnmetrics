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
 * About LearnMetrics: release, maintainer, documentation and support.
 *
 * @package    local_learnmetrics
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('local_learnmetrics_about');

$plugin = core_plugin_manager::instance()->get_plugin_info('local_learnmetrics');
[$from, $to] = $plugin->supported;
$repository = 'https://github.com/biruhlxd/moodle-local_learnmetrics';

$context = [
    'details' => get_string('about_details', 'local_learnmetrics', (object) [
        'release' => $plugin->release,
        'moodle' => intdiv($from, 100) . '.' . ($from % 100) . '–' . intdiv($to, 100) . '.' . ($to % 100),
    ]),
    'maintainer' => [
        ['url' => 'https://biruh.org/', 'name' => get_string('about_website', 'local_learnmetrics')],
    ],
    'support' => [
        ['url' => $repository . '#readme', 'name' => get_string('about_documentation', 'local_learnmetrics')],
        ['url' => $repository . '/issues', 'name' => get_string('about_issues', 'local_learnmetrics')],
        ['url' => $repository . '/blob/main/CHANGES.md', 'name' => get_string('about_changes', 'local_learnmetrics')],
        ['url' => 'https://www.gnu.org/licenses/gpl-3.0.html', 'name' => get_string('about_licence', 'local_learnmetrics')],
    ],
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_learnmetrics/about', $context);
echo $OUTPUT->footer();
