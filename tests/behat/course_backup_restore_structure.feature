@mod @mod_kanbanlearning @core_backup
Feature: Course backup and restore retain Kanban board structure without card data
  In order to reuse a Kanban activity in another course
  As a teacher
  I need the restored activity to keep its columns but not its cards

  Background:
    Given the following "courses" exist:
      | fullname           | shortname |
      | Source course      | C1        |
      | Destination course | C2        |
    And the following config values are set as admin:
      | enableasyncbackup | 0 |
    And I log in as "admin"

  @javascript
  Scenario: Restoring without enrolled users keeps added columns and excludes cards
    Given the following "activity" exists:
      | activity | kanbanlearning |
      | course   | C1             |
      | idnumber | sourcekanban   |
      | name     | Source Kanban  |
      | section  | 1              |
    And I am on the "Source Kanban" "kanbanlearning activity" page
    When I hover ".mod_kanbanlearning_column_container > .mod_kanbanlearning_column:nth-child(2) .mod_kanbanlearning_addcolumn_container" "css_element"
    And I click on ".mod_kanbanlearning_column_container > .mod_kanbanlearning_column:nth-child(2) .mod_kanbanlearning_addcolumn" "css_element"
    And I wait until ".mod_kanbanlearning_column_container > .mod_kanbanlearning_column:nth-child(5)" "css_element" exists
    And I click on ".mod_kanbanlearning_column_container > .mod_kanbanlearning_column:nth-child(2) .mod_kanbanlearning_addcard_link" "css_element"
    And I wait until ".mod_kanbanlearning_column_container .mod_kanbanlearning_card" "css_element" exists
    And I backup "Source course" course using this options:
      | Initial      | Include enrolled users | 0                  |
      | Confirmation | Filename               | kanban-backup.mbz  |
    And I restore "kanban-backup.mbz" backup into a new course using this options:
      | Schema | Course name | Restored course |
    And I am on the "Restored course" course page
    And I click on "Source Kanban" "link"
    Then ".mod_kanbanlearning_column_container > .mod_kanbanlearning_column:nth-child(5)" "css_element" should exist
    And ".mod_kanbanlearning_column_container .mod_kanbanlearning_card" "css_element" should not exist

  @javascript
  Scenario: Restoring without groups changes a group board into a shared board
    Given the following "groups" exist:
      | name    | idnumber | course |
      | Group A | groupa   | C1     |
    And the following "activity" exists:
      | activity  | kanbanlearning |
      | course    | C1             |
      | idnumber  | sourcekanban   |
      | name      | Source Kanban  |
      | section   | 1              |
      | boardmode | 1              |
    When I backup "Source course" course using this options:
      | Initial      | Include enrolled users     | 0                 |
      | Initial      | Include groups and groupings | 0                |
      | Confirmation | Filename                   | kanban-backup.mbz |
    And I restore "kanban-backup.mbz" backup into a new course using this options:
      | Schema | Course name | Restored course |
    And I am on the "Restored course" course page
    And I click on "Source Kanban" "link"
    Then I should see "Shared board" in the ".mod_kanbanlearning_boardselector_current" "css_element"
    And ".mod_kanbanlearning_column_container .mod_kanbanlearning_column" "css_element" should exist
    When I click on ".mod_kanbanlearning_boardselector_button" "css_element"
    Then I should not see "Group A" in the ".mod_kanbanlearning_boardselector_menu" "css_element"
