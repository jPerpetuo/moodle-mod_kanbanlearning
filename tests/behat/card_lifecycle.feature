@mod @mod_kanbanlearning
Feature: Manage cards through the board interface
  In order to keep work organized
  As a teacher
  I need to create, duplicate, move and delete cards from the board

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Terry     | Teacher  | teacher1@example.com |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
    And the following "activity" exists:
      | activity | kanbanlearning  |
      | course   | C1              |
      | idnumber | kanbanlearning1 |
      | name     | Learning Kanban |
      | section  | 1               |

  @javascript
  Scenario: A teacher can create, duplicate, move and delete a card without reloading
    Given I am on the "Learning Kanban" "kanbanlearning activity" page logged in as teacher1
    When I click on ".mod_kanbanlearning_column_container > .mod_kanbanlearning_column:nth-child(2) .mod_kanbanlearning_addcard_link" "css_element"
    And I wait until ".mod_kanbanlearning_column_container > .mod_kanbanlearning_column:nth-child(2) .mod_kanbanlearning_card" "css_element" exists
    Then I should see "1" in the ".mod_kanbanlearning_column_container > .mod_kanbanlearning_column:nth-child(2) .mod_kanbanlearning_cardcount" "css_element"
    When I click on ".mod_kanbanlearning_column_container > .mod_kanbanlearning_column:nth-child(2) .mod_kanbanlearning_card .mod_kanbanlearning_action_menu_trigger" "css_element"
    And I click on ".mod_kanbanlearning_column_container > .mod_kanbanlearning_column:nth-child(2) .mod_kanbanlearning_card [data-action='duplicate_card']" "css_element"
    And I wait until ".mod_kanbanlearning_column_container > .mod_kanbanlearning_column:nth-child(2) .mod_kanbanlearning_card:nth-child(3)" "css_element" exists
    Then I should see "2" in the ".mod_kanbanlearning_column_container > .mod_kanbanlearning_column:nth-child(2) .mod_kanbanlearning_cardcount" "css_element"
    When I click on ".mod_kanbanlearning_column_container > .mod_kanbanlearning_column:nth-child(2) .mod_kanbanlearning_card .mod_kanbanlearning_action_menu_trigger" "css_element"
    And I click on ".mod_kanbanlearning_column_container > .mod_kanbanlearning_column:nth-child(2) .mod_kanbanlearning_card [data-action='move_card']" "css_element"
    And I set the field "mod_kanbanlearning_move_card_column" to "Doing"
    And I press "Move"
    And I wait until ".mod_kanbanlearning_column_container > .mod_kanbanlearning_column:nth-child(3) .mod_kanbanlearning_card" "css_element" exists
    Then I should see "1" in the ".mod_kanbanlearning_column_container > .mod_kanbanlearning_column:nth-child(2) .mod_kanbanlearning_cardcount" "css_element"
    And I should see "1" in the ".mod_kanbanlearning_column_container > .mod_kanbanlearning_column:nth-child(3) .mod_kanbanlearning_cardcount" "css_element"
    When I click on ".mod_kanbanlearning_column_container > .mod_kanbanlearning_column:nth-child(3) .mod_kanbanlearning_card .mod_kanbanlearning_action_menu_trigger" "css_element"
    And I click on ".mod_kanbanlearning_column_container > .mod_kanbanlearning_column:nth-child(3) .mod_kanbanlearning_card [data-action='delete_card']" "css_element"
    And I press "Delete"
    And I wait until ".mod_kanbanlearning_column_container > .mod_kanbanlearning_column:nth-child(3) .mod_kanbanlearning_card" "css_element" does not exist
    Then I should see "0" in the ".mod_kanbanlearning_column_container > .mod_kanbanlearning_column:nth-child(3) .mod_kanbanlearning_cardcount" "css_element"
