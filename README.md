# LearnMetrics (local_learnmetrics)

Learning analytics for Moodle 5.0–5.3 that run entirely inside Moodle: learning time, dashboards and ready-to-use reports, built on Moodle's own Report builder. No data leaves the site.

Other plugins can add to the dashboards, the learning progress page and the reports through the hooks of LearnMetrics (see *Extending*).

## What it does

**Learning time.** A scheduled task (every 15 minutes) turns the standard log into learning time per learner, course and day. The time between two actions of a learner counts towards the course of the first action, when the pause is no longer than the *session gap* (default 30 minutes); a longer pause ends the session, and the time after the last action of a session is not counted. Guests, "log in as", command-line and anonymous actions are left out. The task reads the log in batches from where it stopped and carries sessions across runs; on installation it starts from the last 90 days (setting).

**Time on open pages ("still here" pings).** Moodle logs nothing while a learner works inside a SCORM package or reads a long page, so the log alone misses that time. While a page of a course is open (the course page, an activity, the SCORM player), the page sends a ping every minute, as long as the tab is visible and the learner has used the page (or a frame of it from the same site) in the last 5 minutes. On the SCORM player, the package saving its progress through Moodle's SCORM interface also counts as activity (Storyline, Rise and Captivate save as the learner moves through them, even when the learner only reads or watches; time values, which some packages save on a timer, do not count), and the limit is 15 minutes, for videos; a package served from another site cannot be watched directly, so a visible tab is enough there. Pings are activity points of a session like log entries: they bridge the gaps but are not counted as actions. The task merges log entries and pings in time order, up to two minutes before now, and deletes pings once processed. Pings also show when the learner was **not** there: after an action in a web browser, a pause of more than 3 minutes without pings (the window was minimised, the tab hidden, or the page left alone) counts as 3 minutes, however short the session gap. Actions from the Moodle mobile app (logged as web service calls), which sends no pings, follow the session gap only, and so does time from before pings were first collected (the moment of installing or upgrading to 0.5.0). Setting: *Count time on open pages* (on by default; when off, the session gap alone applies).

**Learning dashboard.** Site administration → Reports → Learning dashboard (`local/learnmetrics:viewdashboard`, managers by default). Filters: period (7, 30, 90, 365 days), course category (with sub-categories), cohort, department.
- Active learners, learning hours, hours per active learner, new enrolments, course completions
- Active learners and learning hours per day; completion of active enrolments; hours by weekday; courses with the most learning time
- Learners needing attention: active enrolment, course not completed, no activity in the course for 14 days or more
- Drill-down: the active learners, new enrolments and completions cards, and each of the courses with the most learning time, open the list of learners behind the figure (learner, course, time spent in the period, last activity, completion status), with the same filters, sortable, paginated and downloadable (CSV, Excel and the other data formats).

**Course dashboard.** In each course: Reports → Learning dashboard (`local/learnmetrics:viewcoursedashboard`: teachers, editing teachers and managers). The same figures for one course, with drill-down, plus the views, distinct learners and learning time of each activity in the period (learning time per activity is recorded from 0.13.0: the time after an action in an activity counts towards it, and "still here" pings continue the activity of the last action in the same course, such as a SCORM package; views are "viewed" actions in the activity, from the standard log, as in Moodle's own activity reports), and the time each SCORM package reported for its learners (the package's own figure, next to the measured learning time; packages that never report time are flagged). In a course in separate groups mode, a user who cannot access all groups sees only the learners of their groups.

**Learning progress for learners.** Each user's profile has *My learning progress* in its Reports section (`local/learnmetrics:viewownprogress`, every authenticated user by default; remove it from the Authenticated user role to hide the page). It shows their learning hours and active days in the period (7, 30, 90, 365 days), their learning hours in total and how many of their courses they have completed; learning hours per day; and each course they are enrolled in, with its completion progress (share of activities completed, as on Moodle's Dashboard), time spent and last activity. Others see a user's progress from that user's profile with `local/learnmetrics:viewuserprogress` in the user's context: managers by default, and parents or mentors given a role with it in the context of the users they follow.

**Departments.** The site dashboard and its drill-down lists can be filtered by department: the values of a field of the user profile chosen in the settings (*Department field*: Department by default, or Institution, City, or a custom profile field of type text or drop-down menu; None turns the filter off). The filter lists the values held by active users and appears when at least one user has a value. A department is a filter on learners, combined with the category and cohort filters.

**Who counts as a learner.** The dashboards count only users with a *learner role* (setting; default the roles with the student archetype) in the course concerned; for time spent outside courses, a learner role in any course. Site administrators are left out unless *Count site administrators* is on. Learning time is still recorded for everyone, and the Report builder sources are not affected (they have their own role and user conditions).

**Report builder sources.** Site administration → Reports → Report builder → Custom reports.
- *Learner activity*: every enrolled learner in every course, with the core course, category, enrolment, user, cohort, completion and course access columns, plus learning time: time spent, learning hours, actions, active days, first and last activity. Time spent, active days and last activity are also filters and conditions.
- *SCORM attempts*: one row per learner and attempt at a SCORM activity, with the course, category and user columns and the attempt: activity name, attempt number, status (passed, failed, completed, incomplete, browsed, not attempted), raw score, total time, first and last access. Read from mod_scorm's own tracks (Moodle 4.3+ tables), for SCORM 1.2 and SCORM 2004; a package with several SCOs gives one row per attempt (best status, a failed test outranking a completion; highest score; total time of all SCOs).

## Extending

Other plugins can add to LearnMetrics with hooks (Moodle's Hooks API, `db/hooks.php`):

- `\local_learnmetrics\hook\dashboard_output`: the site and course dashboards. Add HTML under a table (`add_table_footer()`: trend, courses, attention, activities, scorm), sections after the learners needing attention (`add_section()`) and links at the end (`add_link()`). The hook gives the dashboard with its filters (`get_dashboard()`) and the filters as URL parameters (`get_params()`).
- `\local_learnmetrics\hook\my_progress_output`: the learning progress page of a user. Add sections before the courses.
- `\local_learnmetrics\hook\learner_activity_source`: the Learner activity report source. Add Report builder entities joined to the course and user of each row.

A dashboard's figures can be extended with the same learners: `dashboard::get_scope_sql()` returns the SQL that limits rows to the learners of the dashboard, with its filters.

## How it is built

| Part | Where |
|---|---|
| Learning time engine | `classes/local/time_tracker.php`, task `classes/task/process_logs.php` |
| Pings | AMD module `amd/src/heartbeat.js` (added on course pages by the hook callback in `classes/hook_callbacks.php`, `db/hooks.php`), web service `local_learnmetrics_heartbeat` (`classes/external/heartbeat.php`) |
| Tables | `local_learnmetrics_daily` (user, course, day), `local_learnmetrics_cmtime` (user, activity, day), `local_learnmetrics_course` (totals per user and course), `local_learnmetrics_session` (last action of each user), `local_learnmetrics_ping` (pings not yet processed) |
| Report builder | `classes/reportbuilder/datasource/learner_activity.php` and `scorm_attempts.php`, entities `classes/reportbuilder/local/entities/learning_time.php` and `scorm_attempt.php` |
| Dashboards | `index.php` (site), `course.php` (course), `learners.php` (drill-down); figures in `classes/local/dashboard.php` and `course_dashboard.php` (one scope helper for all figures), list `classes/local/learners_table.php`, charts `classes/local/charts.php` (Moodle's chart API), templates `templates/dashboard.mustache` and `course_dashboard.mustache` |
| Learning progress | `my.php` (template `templates/my_progress.mustache`), figures in `classes/local/my_progress.php`, link in the profile from `lib.php` |
| Hooks | `classes/hook/` |
| Privacy | `classes/privacy/provider.php`: export and deletion per course (learning time, unprocessed pings) and per user (last action); observers remove data of deleted users, courses and activities |

Settings: Site administration → Plugins → Local plugins → LearnMetrics (session gap, days of past activity on installation, count time on open pages, learner roles, count site administrators, department field).

## Upgrading from before 1.0

Up to 0.18, LearnMetrics also included scheduled reports, compliance tracking, at-risk alerts, the dashboards of a teacher's courses, the engagement score, the comparison of groups and the dashboard downloads. Upgrading to 1.0 keeps their data in the database, unused, so that a plugin providing those features can take it over (schedules, compliance requirements, reminders and statuses, alerts, engagement scores, the alert settings and the role permissions of the moved capabilities).

## Development

- Code style: Moodle coding standard (`moodle-plugin-ci phpcs --max-warnings 0`), PHPDoc on everything, English strings in `lang/en` sorted by key.
- Tests: `vendor/bin/phpunit --testsuite local_learnmetrics_testsuite` and Behat (`tests/behat`).
- Without GitHub Actions minutes: open a GitHub codespace on this repository (`.devcontainer`: PHP 8.3, PostgreSQL 17 and MariaDB 11 in Docker as in the workflow, Node 22, Docker for Behat) and run `.devcontainer/run-tests.sh [MOODLE_BRANCH] [pgsql|mariadb] [--no-behat]`, which runs the same checks as the workflow for one Moodle version. GitHub Actions runs the code checks, PHPUnit and Behat on Moodle 5.0–5.3 with PostgreSQL and MariaDB for every push (`.github/workflows/ci.yml`).
- SQL must work on both PostgreSQL and MariaDB/MySQL: with `SELECT DISTINCT`, order only by selected columns; use `$DB->sql_*` helpers for string functions.
- Any new table needs `db/install.xml`, an upgrade step in `db/upgrade.php`, a version bump and privacy metadata.

## Author

Biruh Alemnew <biruh@biruh.org>

## License

GNU GPL v3 or later. See [LICENSE](LICENSE).
