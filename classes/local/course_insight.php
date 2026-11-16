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
 * Course insight: where learners drop off between the activities with completion tracking, and the activities
 * nobody opened in the period.
 *
 * @package    local_learnmetrics
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_insight {
    /** @var course_dashboard The learners and the period, through the course dashboard. */
    protected $dashboard;

    /** @var \course_modinfo The course, as the viewer sees it. */
    protected $modinfo;

    /**
     * Constructor.
     *
     * @param course_dashboard $dashboard The dashboard of the course, with the viewer's groups.
     * @param \course_modinfo $modinfo The course, as the viewer sees it.
     */
    public function __construct(course_dashboard $dashboard, \course_modinfo $modinfo) {
        $this->dashboard = $dashboard;
        $this->modinfo = $modinfo;
    }

    /**
     * The completion funnel: how many learners completed each activity with completion tracking, in course order.
     *
     * The drop of a step is the number of learners lost since the step before it (for the first step, since
     * enrolment); a step completed by more learners than the one before has no drop. Completion is counted as in
     * the activity completion grid: completed with a failing grade does not count.
     *
     * @return array learners (the number of enrolled learners), steps (cmid, name, url, completed, percent, drop,
     *     biggest: true for the step with the largest drop)
     */
    public function get_funnel(): array {
        $grid = new completion_grid($this->dashboard, $this->modinfo);
        $userids = $this->dashboard->get_enrolled_learners();
        $states = $grid->get_states($userids);
        $count = count($userids);
        $previous = $count;
        $steps = [];
        $biggest = null;
        foreach ($grid->get_activities() as $cmid => $cm) {
            $completed = count(array_filter($states, fn($bycm) => ($bycm[$cmid] ?? null) === completion_grid::STATE_COMPLETE));
            $step = (object)[
                'cmid' => $cmid,
                'name' => $cm->get_formatted_name(['escape' => false]),
                'url' => $cm->url,
                'completed' => $completed,
                'percent' => $count ? (int)round(100 * $completed / $count) : 0,
                'drop' => max(0, $previous - $completed),
                'biggest' => false,
            ];
            if ($step->drop > 0 && ($biggest === null || $step->drop > $biggest->drop)) {
                $biggest = $step;
            }
            $steps[] = $step;
            $previous = $completed;
        }
        if ($biggest) {
            $biggest->biggest = true;
        }
        return ['learners' => $count, 'steps' => $steps];
    }

    /**
     * The activities no learner opened or spent time in during the period.
     *
     * Activities without a page of their own, such as text and media areas, are left out: they cannot be opened.
     *
     * @return \stdClass[] In course order: cmid, name, modfullname, iconurl, url.
     */
    public function get_quiet_activities(): array {
        return array_values(array_filter(
            $this->dashboard->get_activity_views($this->modinfo),
            fn($activity) => $activity->url && !$activity->views && !$activity->timespent
        ));
    }
}
