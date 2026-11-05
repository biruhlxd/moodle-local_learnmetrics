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
 * Behat steps of local_learnmetrics.
 *
 * @package    local_learnmetrics
 * @category   test
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

use Behat\Mink\Exception\DriverException;
use Behat\Mink\Exception\ExpectationException;

/**
 * Behat steps of local_learnmetrics.
 *
 * @package    local_learnmetrics
 * @category   test
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_local_learnmetrics extends behat_base {
    /**
     * The URL of a page of the plugin, for "I am on the ... "local_learnmetrics > ..." page".
     *
     * Recognised page names: "Course dashboard" (identifier: the course's short name).
     *
     * @param string $type The page name.
     * @param string $identifier Which instance.
     * @return moodle_url
     */
    protected function resolve_page_instance_url(string $type, string $identifier): moodle_url {
        switch (strtolower($type)) {
            case 'course dashboard':
                return new moodle_url('/local/learnmetrics/course.php', ['id' => $this->get_course_id($identifier)]);
            default:
                throw new Exception('Unrecognised local_learnmetrics page type "' . $type . '."');
        }
    }

    /**
     * Checks that nothing in the main region of the page sticks out to the right of the window.
     *
     * Content inside a scrolling container (a responsive table) may be wider: it scrolls inside its container.
     *
     * @Then /^the main region should fit the window width$/
     * @throws ExpectationException
     */
    public function the_main_region_should_fit_the_window_width(): void {
        if (!$this->running_javascript()) {
            throw new DriverException('Checking the layout requires javascript');
        }
        $script = <<<'EOF'
            return (function() {
                var width = document.documentElement.clientWidth;
                var wide = [];
                document.querySelectorAll('#region-main *').forEach(function(element) {
                    if (element.parentElement.closest('.table-responsive, .no-overflow')) {
                        return;
                    }
                    var box = element.getBoundingClientRect();
                    if (box.width > 0 && box.right > width + 1) {
                        wide.push(element.tagName.toLowerCase() + '.' + String(element.className).split(' ').join('.')
                            + ' (' + Math.round(box.right) + 'px)');
                    }
                });
                return wide.slice(0, 5).join(', ');
            })();
            EOF;
        $wide = $this->evaluate_script($script);
        if ($wide !== '' && $wide !== null) {
            throw new ExpectationException('Wider than the window (' . $this->evaluate_script(
                'return document.documentElement.clientWidth;'
            ) . 'px): ' . $wide, $this->getSession());
        }
    }
}
