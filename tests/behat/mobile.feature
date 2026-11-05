@local @local_learnmetrics @javascript
Feature: Use the learning dashboards on a phone
  In order to follow learning away from my desk
  As a manager, a teacher or a learner
  I need the LearnMetrics pages to fit a phone screen

  Background:
    Given the following "categories" exist:
      | name                                                                 | idnumber |
      | Continuing professional development for community health practitioners | CPD      |
    And the following "courses" exist:
      | fullname                                                            | shortname | category | enablecompletion |
      | Infection prevention and control in primary health care facilities | IPC       | CPD      | 1                |
    And the following "activities" exist:
      | activity | name                                                  | course | completion |
      | page     | Hand hygiene and standard precautions in the clinic   | IPC    | 1          |
    And the following "users" exist:
      | username | firstname   | lastname                   | department                        |
      | teacher1 | Teacher     | One                        | Clinical education                |
      | student1 | Bartholomew | Featherstonehaugh-Worthington | Community health outreach services |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | IPC    | editingteacher |
      | student1 | IPC    | student        |
    And the following "cohorts" exist:
      | name                                                  | idnumber |
      | Community health practitioners enrolled in autumn 2026 | CHP2026  |
    And the following "cohort members" exist:
      | user     | cohort  |
      | student1 | CHP2026 |
    And I am on the "Hand hygiene and standard precautions in the clinic" "page activity" page logged in as "student1"
    And I log out
    And I run the scheduled task "\local_learnmetrics\task\process_logs"
    And I change window size to "360x740"

  Scenario: The pages of a manager fit a phone screen
    Given I log in as "admin"
    When I visit "/local/learnmetrics/index.php"
    Then I should see "Active learners"
    And the main region should fit the window width
    And I visit "/local/learnmetrics/learners.php?metric=activelearners"
    And the main region should fit the window width

  Scenario: The pages of a teacher fit a phone screen
    When I am on the "IPC" "local_learnmetrics > Course dashboard" page logged in as "teacher1"
    Then I should see "Views per activity"
    And the main region should fit the window width

  Scenario: The progress page of a learner fits a phone screen
    Given I log in as "student1"
    When I visit "/local/learnmetrics/my.php"
    Then the main region should fit the window width
