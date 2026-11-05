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

use core\chart_axis;
use core\chart_bar;
use core\chart_base;
use core\chart_line;
use core\chart_pie;
use core\chart_series;

/**
 * Charts of the site and course dashboards, built with Moodle's chart API.
 *
 * @package    local_learnmetrics
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class charts {
    /**
     * All charts of a dashboard, in the order they are shown.
     *
     * @param dashboard $dashboard
     * @param bool $topcourses Include the chart of the courses with the most learning time, when there are any.
     * @return chart_base[] Keyed by name: trend, completion, weekday, topcourses.
     */
    public static function for_dashboard(dashboard $dashboard, bool $topcourses = true): array {
        $charts = [
            'trend' => self::trend($dashboard->get_daily_trend()),
            'completion' => self::completion($dashboard->get_completion_status()),
            'weekday' => self::weekday($dashboard->get_weekday_hours()),
        ];
        if ($topcourses) {
            $top = $dashboard->get_top_courses();
            if ($top) {
                $charts['topcourses'] = self::top_courses($top);
            }
        }
        return $charts;
    }

    /**
     * Active learners (left axis) and learning hours (right axis) per day.
     *
     * @param array $trend As returned by {@see dashboard::get_daily_trend()}.
     * @return chart_line
     */
    public static function trend(array $trend): chart_line {
        $chart = new chart_line();
        $chart->set_title(get_string('chart_trend', 'local_learnmetrics'));
        $chart->set_labels(array_map(
            fn($day) => userdate($day, get_string('strftimedateshort', 'langconfig')),
            array_keys($trend)
        ));
        $chart->add_series(new chart_series(
            get_string('activelearners', 'local_learnmetrics'),
            array_column($trend, 'learners')
        ));
        $hours = new chart_series(get_string('hours', 'local_learnmetrics'), array_column($trend, 'hours'));
        $hours->set_yaxis(1);
        $chart->add_series($hours);

        // The axis at index 0 must exist before the one at index 1 can be set.
        $left = new chart_axis();
        $left->set_label(get_string('activelearners', 'local_learnmetrics'));
        $left->set_min(0);
        $chart->set_yaxis($left, 0);
        $right = new chart_axis();
        $right->set_position(chart_axis::POS_RIGHT);
        $right->set_label(get_string('hours', 'local_learnmetrics'));
        $right->set_min(0);
        $chart->set_yaxis($right, 1);
        return $chart;
    }

    /**
     * Completion of active enrolments.
     *
     * @param int[] $status As returned by {@see dashboard::get_completion_status()}.
     * @return chart_pie
     */
    public static function completion(array $status): chart_pie {
        $chart = new chart_pie();
        $chart->set_doughnut(true);
        $chart->set_title(get_string('chart_completion', 'local_learnmetrics'));
        $chart->set_labels([
            get_string('completed', 'local_learnmetrics'),
            get_string('inprogress', 'local_learnmetrics'),
            get_string('notstarted', 'local_learnmetrics'),
        ]);
        $chart->add_series(new chart_series(
            get_string('enrolments', 'local_learnmetrics'),
            [$status['completed'], $status['inprogress'], $status['notstarted']]
        ));
        return $chart;
    }

    /**
     * Learning hours by day of the week.
     *
     * @param float[] $hours Monday first, as returned by {@see dashboard::get_weekday_hours()}.
     * @return chart_bar
     */
    public static function weekday(array $hours): chart_bar {
        $chart = new chart_bar();
        $chart->set_title(get_string('chart_weekday', 'local_learnmetrics'));
        $chart->set_labels(array_map(
            fn($day) => get_string($day, 'calendar'),
            ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday']
        ));
        $chart->add_series(new chart_series(get_string('hours', 'local_learnmetrics'), array_values($hours)));
        return $chart;
    }

    /**
     * Learning hours of one learner per day.
     *
     * @param float[] $hours Day start => hours, as returned by {@see my_progress::get_daily_hours()}.
     * @return chart_bar
     */
    public static function daily_hours(array $hours): chart_bar {
        $chart = new chart_bar();
        $chart->set_title(get_string('chart_dailyhours', 'local_learnmetrics'));
        $chart->set_labels(array_map(
            fn($day) => userdate($day, get_string('strftimedateshort', 'langconfig')),
            array_keys($hours)
        ));
        $chart->add_series(new chart_series(get_string('hours', 'local_learnmetrics'), array_values($hours)));
        return $chart;
    }

    /**
     * The courses with the most learning time.
     *
     * @param \stdClass[] $top As returned by {@see dashboard::get_top_courses()}.
     * @return chart_bar
     */
    public static function top_courses(array $top): chart_bar {
        $chart = new chart_bar();
        $chart->set_horizontal(true);
        $chart->set_title(get_string('chart_topcourses', 'local_learnmetrics'));
        $chart->set_labels(array_map(fn($course) => shorten_text($course->fullname, 40), $top));
        $chart->add_series(new chart_series(get_string('hours', 'local_learnmetrics'), array_column($top, 'hours')));
        return $chart;
    }
}
