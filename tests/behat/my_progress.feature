@local @local_learnmetrics
Feature: Learners see their own progress
  In order to know where I stand
  As a learner
  I need to see my learning time and my courses

  Background:
    Given the following "courses" exist:
      | fullname        | shortname | enablecompletion |
      | Safety training | SAFE      | 1                |
    And the following "users" exist:
      | username | firstname | lastname |
      | student1 | Student   | One      |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | SAFE   | student |

  Scenario: A learner opens their progress from their profile
    Given I log in as "student1"
    When I follow "Profile" in the user menu
    And I follow "My learning progress"
    Then I should see "My learning progress"
    And I should see "Learning hours in total"
    And I should see "0 of 1"
    And I should see "Safety training" in the "Safety training" "table_row"

  Scenario: A manager sees the progress of a learner from their profile
    Given I log in as "admin"
    When I navigate to "Users > Accounts > Browse list of users" in site administration
    And I follow "Student One"
    And I follow "Learning progress"
    Then I should see "Learning progress of Student One"
    And I should see "Safety training"
