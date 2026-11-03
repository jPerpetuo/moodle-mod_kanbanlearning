@mod @mod_kanbanlearning
Feature: Group boards enforce membership
  In order to keep group work private
  As a group member
  I need access to my group's board but not another group's board

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                 |
      | teacher1 | Terry     | Teacher  | teacher1@example.com  |
      | student1 | Sam       | Student  | student1@example.com  |
      | student2 | Alex      | Learner  | student2@example.com  |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
      | student2 | C1     | student        |
    And the following "groups" exist:
      | name    | idnumber | course |
      | Group A | groupa   | C1     |
      | Group B | groupb   | C1     |
    And the following "group members" exist:
      | user     | group   |
      | student1 | groupa   |
      | student2 | groupb   |
    And the following "activity" exists:
      | activity  | kanbanlearning  |
      | course    | C1              |
      | idnumber  | kanbanlearning1 |
      | name      | Learning Kanban |
      | section   | 1               |
      | boardmode | 1               |

  @javascript
  Scenario: A group member cannot access another group's board by URL
    Given I am on the "Learning Kanban" "kanbanlearning activity" page logged in as teacher1
    When I click on ".mod_kanbanlearning_boardselector_button" "css_element"
    And I click on "Group B" "link"
    Then I should see "Group B" in the ".mod_kanbanlearning_boardselector_current" "css_element"
    When I am on the "Learning Kanban" "kanbanlearning activity" page logged in as student1
    Then ".mod_kanbanlearning_column_container .mod_kanbanlearning_column" "css_element" should exist
    Then the direct URL for group "groupb" should be denied
    And a student outside group "groupb" cannot move an assigned card or add a column
