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
 * English strings for local_learnmetrics.
 *
 * @package    local_learnmetrics
 * @copyright  2026 Biruh Alemnew <biruh@biruh.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['actions'] = 'Actions';
$string['activedays'] = 'Active days';
$string['activelearners'] = 'Active learners';
$string['activitycompletion'] = 'Activity completion';
$string['activitycompletion_desc'] = 'Each learner of the course against each activity with completion tracking: ✓ completed (or passed), ✗ completed with a failing grade, – not completed. Under each activity, the share of the learners who completed it.';
$string['activityviews'] = 'Views per activity';
$string['activityviews_desc'] = 'How often learners opened each activity in the period, and how many different learners did, from the site log.';
$string['allcategories'] = 'All categories';
$string['allcohorts'] = 'All cohorts';
$string['alldepartments'] = 'All';
$string['apply'] = 'Apply';
$string['attemptnumber'] = 'Attempt';
$string['attempts'] = 'Attempts';
$string['backfilldays'] = 'Days of past activity to include';
$string['backfilldays_desc'] = 'When the plugin is installed, learning time is calculated from this many days of the existing log. Later changes have no effect.';
$string['backtodashboard'] = 'Back to the learning dashboard';
$string['buildyourown'] = 'Build your own reports from the "Learner activity" report source in';
$string['chart_completion'] = 'Completion of active enrolments';
$string['chart_dailyhours'] = 'Learning hours per day';
$string['chart_topcourses'] = 'Courses with the most learning time';
$string['chart_trend'] = 'Active learners and learning hours per day';
$string['chart_weekday'] = 'Learning hours by day of the week';
$string['completed'] = 'Completed';
$string['completedon'] = 'Completed on {$a}';
$string['completedpercent'] = '{$a}% completed';
$string['completion_complete'] = 'Completed';
$string['completion_fail'] = 'Completed, failed';
$string['completion_none'] = 'Not completed';
$string['completionnotenabled'] = 'Completion tracking is not enabled in this course.';
$string['completionstatus'] = 'Completion status';
$string['coursecompletions'] = 'Course completions';
$string['coursedashboard'] = 'Learning dashboard';
$string['coursescompleted'] = 'Courses completed';
$string['dashboard'] = 'Learning dashboard';
$string['departmentfield'] = 'Department field';
$string['departmentfield_desc'] = 'The field of the user profile that groups learners by department, team or site, such as Department, Institution or a custom profile field (text or drop-down menu). The site dashboard, its drill-down lists and the scheduled reports can then be filtered by its values. None turns the filter off.';
$string['enrolments'] = 'Enrolments';
$string['firstaccess'] = 'First access';
$string['firstactivity'] = 'First activity';
$string['heartbeat'] = 'Count time on open pages';
$string['heartbeat_desc'] = 'While a learner has a page of a course open (such as a SCORM package, a long text or a video), the page tells Moodle every minute that the learner is still there, as long as the browser tab is visible and the learner has used the page in the last 5 minutes. This time then counts as learning time, although Moodle logs nothing while the page is open. Pings also show when the learner was not there: after an action in a web browser, a pause of more than 3 minutes without pings (the window was minimised, hidden or left alone) counts as 3 minutes. Actions in the mobile app, which does not send pings, follow the session gap only.';
$string['hours'] = 'Hours';
$string['hoursinperiod'] = 'Learning hours in the period';
$string['hoursperlearner'] = 'Hours per active learner';
$string['hoursspent'] = 'Learning hours';
$string['includeadmins'] = 'Count site administrators';
$string['includeadmins_desc'] = 'Count site administrators on the dashboards when they hold a learner role. Off by default, so testing a course does not count as learning.';
$string['inprogress'] = 'In progress';
$string['lastaccess'] = 'Last access';
$string['lastactivity'] = 'Last activity';
$string['lastndays'] = 'Last {$a} days';
$string['learner'] = 'Learner';
$string['learneractivity'] = 'Learner activity';
$string['learnercount'] = '{$a} learners';
$string['learnerroles'] = 'Learner roles';
$string['learnerroles_desc'] = 'The site and course dashboards count only users with one of these roles in the course concerned; time spent outside courses counts for users with one of these roles in any course. Learning time is recorded for everyone, and the Report builder sources are not affected. If no role is chosen, every user is counted.';
$string['learners'] = 'Learners';
$string['learninghours'] = 'Learning hours';
$string['learningtime'] = 'Learning time';
$string['learnmetrics:viewcoursedashboard'] = 'View the learning dashboard of a course';
$string['learnmetrics:viewdashboard'] = 'View the learning dashboard';
$string['learnmetrics:viewownprogress'] = 'View one\'s own learning progress';
$string['learnmetrics:viewuserprogress'] = 'View the learning progress of other users';
$string['lessonstatus'] = 'Status';
$string['mycourses'] = 'Courses';
$string['myprogress'] = 'My learning progress';
$string['needingattention'] = 'Learners needing attention';
$string['needingattention_desc'] = 'Learners with an active enrolment who have not completed the course and have not been active in it for {$a} days or more. Longest inactive first.';
$string['newenrolments'] = 'New enrolments';
$string['noactivities'] = 'There are no activities in this course.';
$string['nocompletionactivities'] = 'No activity of this course has completion tracking.';
$string['nocourses'] = 'Not enrolled in any course.';
$string['nofm'] = '{$a->n} of {$a->m}';
$string['nogroups'] = 'The course is in separate groups mode and you are not a member of any group, so no learners are shown.';
$string['nolearners'] = 'There are no learners in this course.';
$string['noneneedingattention'] = 'No learners need attention.';
$string['noreportedtime'] = 'Does not report time';
$string['notcompleted'] = 'Not completed';
$string['notstarted'] = 'Not started';
$string['notyetprocessed'] = 'Learning time has not been calculated yet. It is calculated by a background task every 15 minutes, starting with the recent past.';
$string['outsidecourses'] = 'Outside courses';
$string['period'] = 'Period';
$string['pluginname'] = 'LearnMetrics';
$string['privacy:metadata:activedays'] = 'The number of days with activity.';
$string['privacy:metadata:cmid'] = 'The activity.';
$string['privacy:metadata:cmtime'] = 'Learning time and number of actions of a user in an activity on one day, calculated from the standard log and the "still here" pings.';
$string['privacy:metadata:course'] = 'The total learning time and activity of a user in a course.';
$string['privacy:metadata:courseid'] = 'The course.';
$string['privacy:metadata:daily'] = 'The learning time and activity of a user in a course on each day.';
$string['privacy:metadata:day'] = 'The day.';
$string['privacy:metadata:events'] = 'The number of logged actions.';
$string['privacy:metadata:firstactivity'] = 'The time of the first activity.';
$string['privacy:metadata:lastaction'] = 'The time of the last logged action.';
$string['privacy:metadata:lastactivity'] = 'The time of the last activity.';
$string['privacy:metadata:ping'] = 'A signal that a user still had a page of a course open, kept until it has been added to the learning time.';
$string['privacy:metadata:pingtime'] = 'The time of the signal.';
$string['privacy:metadata:session'] = 'The last logged action of a user, used to measure sessions that span two runs of the background task.';
$string['privacy:metadata:timespent'] = 'The learning time, in seconds.';
$string['privacy:metadata:userid'] = 'The user.';
$string['privacy:metadata:web'] = 'Whether the last action came from a web browser, which sends "still here" pings, or from the mobile app.';
$string['privacy:pings'] = 'Pages still open';
$string['progress'] = 'Progress';
$string['progressof'] = 'Learning progress of {$a}';
$string['progresspercent'] = 'Progress (%)';
$string['reportedtime'] = 'Time reported by the package';
$string['reportedtimeperlearner'] = 'Per learner';
$string['scoreraw'] = 'Score';
$string['scormattempt'] = 'SCORM attempt';
$string['scormattempts'] = 'SCORM attempts';
$string['scormname'] = 'SCORM activity';
$string['scormnamewithlink'] = 'SCORM activity with link';
$string['scormtime'] = 'Time in SCORM packages';
$string['scormtime_desc'] = 'The time each SCORM package reported for its learners, for the attempts used in the period. This is the package\'s own figure: it counts from opening to closing the package, so it can include time the package was left open, and some packages do not report time at all. The learning time above is measured from the log and from the "still here" pings, for the whole course.';
$string['sessiongap'] = 'Session gap';
$string['sessiongap_desc'] = 'The longest pause between two actions of a learner that still counts as learning time. A longer pause ends the session; the time after the last action of a session is not counted.';
$string['task_processlogs'] = 'Calculate learning time from the log';
$string['timespent'] = 'Time spent';
$string['timespentinperiod'] = 'Time spent in the period';
$string['totallearninghours'] = 'Learning hours in total';
$string['totaltime'] = 'Total time';
$string['userprogress'] = 'Learning progress';
$string['viewactivitycompletion'] = 'Activity completion of each learner';
$string['viewlearners'] = 'View the learners';
$string['views'] = 'Views';
$string['yourgroupsonly'] = 'The course is in separate groups mode: only the learners in your groups are counted.';
