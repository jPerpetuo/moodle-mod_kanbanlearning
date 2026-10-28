@mod @mod_kanbanlearning @core_backup
Feature: Course import preserves Kanban board structure and group mode
  In order to reuse a Kanban activity in another course
  As a teacher
  I need imported boards to respect the group inclusion setting

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Terry     | Teacher  | teacher1@example.com |
    And the following "courses" exist:
      | fullname           | shortname |
      | Source course      | C1        |
      | Destination course | C2        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | teacher1 | C2     | editingteacher |
    And the following "groups" exist:
      | name    | course |
      | Group A | C1     |
      | Group B | C1     |
    And the following "activity" exists:
      | activity | kanbanlearning  |
      | course   | C1              |
      | idnumber | sourcekanban    |
      | name     | Source Kanban   |
      | section  | 1               |
      | boardmode| 1               |

  @javascript
  Scenario: Imported group boards retain their groups when groups are included
    Given I am on the "Source course" course page logged in as teacher1
    When I import "Source course" course into "Destination course" course using this options:
      | Initial | Include groups and groupings | 1 |
    And I click on "Source Kanban" "link"
    And I click on ".mod_kanbanlearning_boardselector_button" "css_element"
    Then I should see "Group A" in the ".mod_kanbanlearning_boardselector_menu" "css_element"
    And I should see "Group B" in the ".mod_kanbanlearning_boardselector_menu" "css_element"

  @javascript
  Scenario: Imported group boards become shared when groups are excluded
    Given I am on the "Source course" course page logged in as teacher1
    When I import "Source course" course into "Destination course" course using this options:
      | Initial | Include groups and groupings | 0 |
    And I click on "Source Kanban" "link"
    Then ".mod_kanbanlearning_boardselector" "css_element" should not exist
    And ".mod_kanbanlearning_column_container .mod_kanbanlearning_column" "css_element" should exist
