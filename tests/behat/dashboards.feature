@local @local_learnmetrics
Feature: See the learning dashboards
  In order to follow how learners use the courses
  As a manager or a teacher
  I need to see the site and course learning dashboards

  Background:
    Given the following "courses" exist:
      | fullname | shortname | enablecompletion |
      | Course 1 | C1        | 1                |
    And the following "activities" exist:
      | activity | name         | course |
      | page     | Welcome page | C1     |
    And the following "users" exist:
      | username | firstname | lastname |
      | teacher1 | Teacher   | One      |
      | student1 | Student   | One      |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |

  Scenario: A manager sees the site learning dashboard
    Given I log in as "admin"
    When I navigate to "Reports > Learning dashboard" in site administration
    Then I should see "Learning time has not been calculated yet."
    And I should see "Active learners"
    And I should see "Course completions"
    And I should see "No learners need attention."
    And I set the field "Period" to "Last 7 days"
    And I press "Apply"
    And I should see "Active learners"

  Scenario: The dashboard counts learning time once the logs are processed
    Given I am on the "Welcome page" "page activity" page logged in as "student1"
    And I log out
    And I run the scheduled task "\local_learnmetrics\task\process_logs"
    When I log in as "admin"
    And I navigate to "Reports > Learning dashboard" in site administration
    Then I should not see "Learning time has not been calculated yet."
    And I should see "Active learners"

  Scenario: A teacher sees the course learning dashboard
    When I am on the "C1" "local_learnmetrics > Course dashboard" page logged in as "teacher1"
    Then I should see "Active learners"
    And I should see "Views per activity"
    And I should see "Welcome page"
    And I should see "No learners need attention."

  Scenario: A manager filters the site dashboard by department
    Given the following "users" exist:
      | username | firstname | lastname | department |
      | sales1   | Sam       | Sales    | Sales      |
      | finance1 | Fay       | Finance  | Finance    |
    And I log in as "admin"
    When I navigate to "Reports > Learning dashboard" in site administration
    And I set the field "Department" to "Sales"
    And I press "Apply"
    Then the field "Department" matches value "Sales"
    And I should see "Active learners"

  Scenario: A teacher sees the activity completion of each learner
    Given the following "activities" exist:
      | activity | name          | course | completion |
      | page     | Safety basics | C1     | 1          |
    And I am on the "C1" "local_learnmetrics > Course dashboard" page logged in as "teacher1"
    When I follow "Activity completion of each learner"
    Then I should see "Activity completion"
    And I should see "0% completed"
    And I should see "Student One"
    And I should not see "Teacher One" in the "region-main" "region"
