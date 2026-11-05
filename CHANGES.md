# Changes

## 1.0.0 (2026-11-10)

- Scheduled reports, compliance, at-risk alerts, the dashboards of a teacher's courses, the engagement score, the comparison of groups and the downloads of the dashboard tables are no longer part of this plugin. Other plugins can add such features through the hooks of LearnMetrics.
- Upgrading keeps the data of those features in the database, unused, for a plugin that provides them to take over (with the role permissions of `local/learnmetrics:manageschedules` and `local/learnmetrics:managecompliance`, kept in the setting `movedcapabilities`).
- `dashboard::get_scope_sql()` and `dashboard::get_courseid()` let other plugins count the same learners as a dashboard.

## 0.18.0 (2026-11-09)

- Hooks for other plugins: `dashboard_output` (sections, links and table footers on the site and course dashboards), `my_progress_output` (sections on the learning progress page) and `learner_activity_source` (entities in the Learner activity report source).

## 0.17.1 (2026-11-08)

- The filters of the dashboards fit a phone screen, also with long category and cohort names. Behat checks every page at phone width.

## 0.17.0 (2026-11-08)

- Compare groups: cohorts, departments or course categories side by side, with the figures of the dashboard, charts and a download.

## 0.16.0 (2026-11-07)

- Engagement score from 0 to 100 for each learner in each course over the last 30 days, refreshed every night: on the dashboards and in the Learner activity and Learners at risk sources.

## 0.15.0 (2026-11-06)

- Every table of the site, course and teacher dashboards can be downloaded in Moodle's data formats, with the dashboard's filters.

## 0.14.0 (2026-11-05)

- Dashboards of a teacher's courses: *Learning dashboards of my courses* in a teacher's profile (Reports section) lists the courses they are enrolled in and can see the learning dashboard of, with each course's active learners, learning hours, completions and learners needing attention in the period, and a link to its dashboard. In a course in separate groups mode, a teacher who cannot access all groups sees the figures of their groups.

## 0.13.0 (2026-11-04)

- Learning time per activity: the time engine also counts the time of each learner in each activity, per day (new table `local_learnmetrics_cmtime`): the time after an action in an activity counts towards it, and "still here" pings continue the activity of the last action in the same course, such as a SCORM package. The course dashboard shows the learning time of each activity in the period, next to its views and learners. Recorded from the upgrade on: earlier time is not split by activity.
- The last action of each user records its activity (new field `cmid` in `local_learnmetrics_session`). A deleted activity's time is deleted.

## 0.12.0 (2026-11-03)

- New Report builder source *Compliance*: one row per user of each enabled compliance requirement, with the course, category and user columns and the requirement's name, status, enrolment, required since, due date, completion and expiry, with filters on status and dates. The users and their dates are stored in the new table `local_learnmetrics_compuser` by the new hourly task *Refresh compliance for reports* (and when a requirement is saved); the status is worked out when the report is shown.
- New Report builder source *Learners at risk*: Learner activity limited to the learners at risk, as on the dashboards and in the at-risk alerts (learner role, active enrolment, visible course not completed, no activity for the *Days without activity*).
- Scheduled PDF reports have a compliance section: each enabled requirement (of the course, for a course report, limited to the recipient's groups in separate groups mode) with the share of compliant users and how many are not yet completed, overdue or expired.

## 0.11.0 (2026-11-02)

- Department filter: the site dashboard, its drill-down lists and scheduled reports of the site dashboard can be filtered by the values of a field of the user profile, chosen in the new setting *Department field* (Department by default; Institution, City, or a custom profile field of type text or drop-down menu; or None). New field `department` in `local_learnmetrics_schedule`.

## 0.10.0 (2026-11-01)

- Learning progress for learners: *My learning progress* in each user's profile (Reports section) shows their learning hours and active days in the period, their learning hours in total, courses completed, learning hours per day, the compliance requirements that apply to them (status, due date, completion, expiry) and each of their courses with its completion progress, time spent and last activity. New capabilities `local/learnmetrics:viewownprogress` (every authenticated user) and `local/learnmetrics:viewuserprogress` (in the user's context: managers, and parents or mentors given a role with it), to see another user's progress from their profile.
- Behat tests for the dashboards, scheduled reports, compliance and the learning progress page, run by CI.

## 0.9.0 (2026-10-31)

- Compliance reminders: each requirement can remind its users some days (3, 7, 14 or 30) before the course is due and when it is overdue, and, when completions expire, before their completion expires and when it has expired. Each reminder is sent once per user and due date, so the next recertification gets its own. New task "Send compliance reminders", daily at 08:45; new message provider "Reminders about courses you are required to complete", so users choose web, email or app in their notification preferences; messages in each user's language.
- Reminders are off for requirements created before 0.9.0, so upgrading sends nothing until they are turned on; new requirements remind 7 days before by default. New field `reminddays` and table `local_learnmetrics_reminder`.

## 0.8.0 (2026-10-30)

- Compliance tracking: Site administration → Reports → Compliance (`local/learnmetrics:viewdashboard`; adding, editing and deleting requirements needs the new capability `local/learnmetrics:managecompliance`, managers), also linked from the site dashboard. A requirement is a course (with completion tracking) that the members of some cohorts, or else the learners enrolled in the course, must complete within a time (optional) of when it applies to them, and again when their completion expires (optional validity, for recertification). New table `local_learnmetrics_requirement`.
- Each user's status is worked out from Moodle's course completions: compliant, expiring soon (within 30 days), not yet completed, overdue or expired. The overview shows each requirement's share of compliant users and how many users have each status; each requirement lists its users (required since, due date, completion, expiry, status, and whether they are enrolled), filtered by status and downloadable (CSV, Excel and the other data formats).
- Nobody is counted from before a requirement was created, so adding one does not make everyone overdue at once. A deleted course deletes its requirements; a deleted cohort is removed from them (a requirement left without cohorts is disabled).

## 0.7.0 (2026-10-29)

- At-risk alerts (off by default; Site administration → Plugins → Local plugins → LearnMetrics): every morning, learners who have an active enrolment in a course, have not completed it and have not been active in it for some days (setting, default 14) get a reminder with a link to the course, and the teachers of the course (enrolled users who can see its learning dashboard) get the list of these learners, limited to their groups in separate groups mode. Learners and teachers can each be turned off.
- Each learner is alerted once per period of inactivity, and again only after they have been active and stopped again (new table `local_learnmetrics_alert`). Learners inactive for more than 30 days longer than the alert days are not alerted, so turning alerts on does not message everyone who left long ago; nor are learners in hidden courses or courses that have ended.
- Alerts are Moodle notifications (new message providers "Reminders when you have not been active in a course" and "Learners at risk in your courses"), sent in each user's language; users choose web, email or app in their notification preferences. New task "Send at-risk alerts", daily at 08:30.

## 0.6.0 (2026-10-28)

- Scheduled dashboard reports by email: the site learning dashboard (with its period, category and cohort) or the dashboard of a course, emailed as a PDF every week (on a day of the week) or every month (on a day of the month; the last day in shorter months), at an hour of the day in the site time zone. Site administration → Reports → Scheduled dashboard reports, also linked from the site dashboard. New capability `local/learnmetrics:manageschedules` (managers), new table `local_learnmetrics_schedule`, new hourly task "Email the scheduled dashboard reports".
- Recipients are chosen users and the holders of chosen roles (in the system for the site dashboard; in the course, its categories or the system for a course dashboard). Each recipient must be able to see the dashboard when the report is sent; others are skipped. A course report follows each recipient's groups in separate groups mode, as the course dashboard does.
- The PDF (A4, built on the server with Moodle's PDF library) has the headline figures, bar charts of learning hours and active learners per day and of hours by weekday, completion status, the courses with the most learning time and the learners needing attention. The email links to the live dashboard. Each recipient gets the email and the PDF in their own language. The PDF uses the site's PDF font (`$CFG->pdfexportfont`, FreeSans by default), so a site can add a font for scripts FreeSans does not cover, such as Ethiopic. "Send now to me" sends a report to yourself to check it.
- The learners behind the figures can already be emailed as CSV or Excel with Moodle's own Report builder schedules, from reports built on the LearnMetrics report sources.

## 0.5.0 (2026-10-27)

- A pause without pings no longer counts as learning time: after an action in a web browser, a pause of more than 3 minutes without "still here" pings counts as 3 minutes. Found on a real site: a SCORM package left open in a minimised window for 23 minutes was counted in full, because the launch and the exit were within the 30-minute session gap. The mobile app, which sends no pings, keeps the session gap rule, and so does time from before pings were first collected (installing or upgrading to this version).
- The last action of each user records whether it came from a web browser (new field `web`), so the rule also applies to sessions that span two runs of the task.

## 0.4.0 (2026-10-27)

- Time in SCORM packages is counted more reliably: on the SCORM player, the package saving its progress through Moodle's SCORM interface counts as the learner being active, so pings continue while the learner reads or watches inside Storyline, Rise or Captivate packages (found when testing on a real site: pings stopped after five minutes without mouse or keyboard input). Pointer events count too, and the SCORM player allows 15 minutes without activity, for videos.
- Course dashboard: "Time in SCORM packages" shows the time each package reported for its learners, next to the measured learning time, and flags packages that never report time.
- Views per activity count only "viewed" actions, as in Moodle's own activity reports.

## 0.3.0 (2026-10-26)

- Fixed: the site dashboard failed with "Missing y axis at index lower than 1" (trend chart). Charts are now built in `local\charts` and every chart is rendered in the tests.
- Fixed: the Learner activity report source failed on Moodle 5.0 and 5.1 (entity elements are now added by entity name).
- Drill-down from the dashboard figures (active learners, new enrolments, course completions, courses with the most learning time) to the learners behind them: `learners.php`, sortable, paginated, downloadable, with the dashboard's filters.
- Course dashboard for teachers (`course.php`, in the course's Reports menu), with views per activity and separate groups support. New capability `local/learnmetrics:viewcoursedashboard`.
- New Report builder source "SCORM attempts" (SCORM 1.2 and 2004): status, score, total time, first and last access per learner and attempt.
- The dashboards count only learners: new settings *Learner roles* (default: student archetype) and *Count site administrators* (off).
- Time on open pages: "still here" pings every minute from course pages (including the SCORM player) while the learner uses them, through the new web service `local_learnmetrics_heartbeat`, stored in the new table `local_learnmetrics_ping` until the time engine has merged them with the log. New setting *Count time on open pages* (on).

## 0.1.0 (2026-10-26)

- Learning time engine from the standard log, Report builder source "Learner activity", site learning dashboard.
