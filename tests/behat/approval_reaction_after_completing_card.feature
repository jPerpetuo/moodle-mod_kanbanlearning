@mod @mod_kanbanlearning
Feature: Approval reactions are available on cards completed during a session
  In order to give feedback on completed work
  As a teacher
  I need to add a reaction to a card without reloading the board

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Terry     | Teacher  | teacher1@example.com |
      | student1 | Sam       | Student  | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "activity" exists:
      | activity       | kanbanlearning       |
      | course         | C1                   |
      | idnumber       | kanbanlearning1      |
      | name           | Learning Kanban      |
      | section        | 1                    |
      | approval_seals | 1                    |
      | boardmode     | 0                    |

  @javascript
  Scenario: A newly created card exposes the reaction menu as soon as it is completed
    Given I am on the "Learning Kanban" "kanbanlearning activity" page logged in as teacher1
    When I click on ".mod_kanbanlearning_column_container > .mod_kanbanlearning_column:nth-child(2) .mod_kanbanlearning_addcard_link" "css_element"
    And I wait until ".mod_kanbanlearning_column_container > .mod_kanbanlearning_column:nth-child(2) .mod_kanbanlearning_card" "css_element" exists
    And I click on ".mod_kanbanlearning_column_container > .mod_kanbanlearning_column:nth-child(2) .mod_kanbanlearning_card .mod_kanbanlearning_action_menu_trigger" "css_element"
    Then ".mod_kanbanlearning_approval_seal_menu" "css_element" in the ".mod_kanbanlearning_column_container > .mod_kanbanlearning_column:nth-child(2) .mod_kanbanlearning_card" "css_element" should not be visible
    When I click on ".mod_kanbanlearning_column_container > .mod_kanbanlearning_column:nth-child(2) .mod_kanbanlearning_complete_card a" "css_element"
    And I wait until ".mod_kanbanlearning_column_container > .mod_kanbanlearning_column:last-child .mod_kanbanlearning_card" "css_element" exists
    And I click on ".mod_kanbanlearning_column_container > .mod_kanbanlearning_column:last-child .mod_kanbanlearning_card .mod_kanbanlearning_action_menu_trigger" "css_element"
    Then ".mod_kanbanlearning_approval_seal_menu" "css_element" in the ".mod_kanbanlearning_column_container > .mod_kanbanlearning_column:last-child .mod_kanbanlearning_card" "css_element" should be visible

  @javascript
  Scenario: A teacher can apply a reaction and see it on the card without reloading
    Given I am on the "Learning Kanban" "kanbanlearning activity" page logged in as teacher1
    When I click on ".mod_kanbanlearning_column_container > .mod_kanbanlearning_column:nth-child(2) .mod_kanbanlearning_addcard_link" "css_element"
    And I wait until ".mod_kanbanlearning_column_container > .mod_kanbanlearning_column:nth-child(2) .mod_kanbanlearning_card" "css_element" exists
    And I click on ".mod_kanbanlearning_column_container > .mod_kanbanlearning_column:nth-child(2) .mod_kanbanlearning_card .mod_kanbanlearning_action_menu_trigger" "css_element"
    And I click on ".mod_kanbanlearning_column_container > .mod_kanbanlearning_column:nth-child(2) .mod_kanbanlearning_card .mod_kanbanlearning_complete_card a" "css_element"
    And I wait until ".mod_kanbanlearning_column_container > .mod_kanbanlearning_column:last-child .mod_kanbanlearning_card" "css_element" exists
    And I click on ".mod_kanbanlearning_column_container > .mod_kanbanlearning_column:last-child .mod_kanbanlearning_card .mod_kanbanlearning_action_menu_trigger" "css_element"
    And I click on ".mod_kanbanlearning_approval_seal_menu [data-action='toggle_approval_seal']" "css_element"
    And I wait "1" seconds
    And I click on ".mod_kanbanlearning_approval_seal_menu [data-action='approval_seal'][data-seal='approved']" "css_element"
    Then ".mod_kanbanlearning_approval_seal" "css_element" should exist
    And I should see "✅" in the ".mod_kanbanlearning_approval_seal" "css_element"

  @javascript
  Scenario: A teacher can change an existing reaction without reloading
    Given I am on the "Learning Kanban" "kanbanlearning activity" page logged in as teacher1
    When I click on ".mod_kanbanlearning_column_container > .mod_kanbanlearning_column:nth-child(2) .mod_kanbanlearning_addcard_link" "css_element"
    And I wait until ".mod_kanbanlearning_column_container > .mod_kanbanlearning_column:nth-child(2) .mod_kanbanlearning_card" "css_element" exists
    And I click on ".mod_kanbanlearning_column_container > .mod_kanbanlearning_column:nth-child(2) .mod_kanbanlearning_card .mod_kanbanlearning_action_menu_trigger" "css_element"
    And I click on ".mod_kanbanlearning_column_container > .mod_kanbanlearning_column:nth-child(2) .mod_kanbanlearning_card .mod_kanbanlearning_complete_card a" "css_element"
    And I wait until ".mod_kanbanlearning_column_container > .mod_kanbanlearning_column:last-child .mod_kanbanlearning_card" "css_element" exists
    And I click on ".mod_kanbanlearning_column_container > .mod_kanbanlearning_column:last-child .mod_kanbanlearning_card .mod_kanbanlearning_action_menu_trigger" "css_element"
    And I click on ".mod_kanbanlearning_approval_seal_menu [data-action='toggle_approval_seal']" "css_element"
    And I wait "1" seconds
    And I click on ".mod_kanbanlearning_approval_seal_menu [data-action='approval_seal'][data-seal='approved']" "css_element"
    And I should see "✅" in the ".mod_kanbanlearning_approval_seal" "css_element"
    And I click on ".mod_kanbanlearning_approval_seal_menu [data-action='toggle_approval_seal']" "css_element"
    And I wait "1" seconds
    And I click on ".mod_kanbanlearning_approval_seal_menu [data-action='approval_seal'][data-seal='clap']" "css_element"
    Then I should see "👏" in the ".mod_kanbanlearning_approval_seal" "css_element"

  @javascript
  Scenario: A student can see a teacher reaction but cannot change it
    Given I am on the "Learning Kanban" "kanbanlearning activity" page logged in as teacher1
    When I click on ".mod_kanbanlearning_column_container > .mod_kanbanlearning_column:nth-child(2) .mod_kanbanlearning_addcard_link" "css_element"
    And I wait until ".mod_kanbanlearning_column_container > .mod_kanbanlearning_column:nth-child(2) .mod_kanbanlearning_card" "css_element" exists
    And I click on ".mod_kanbanlearning_column_container > .mod_kanbanlearning_column:nth-child(2) .mod_kanbanlearning_card .mod_kanbanlearning_action_menu_trigger" "css_element"
    And I click on ".mod_kanbanlearning_column_container > .mod_kanbanlearning_column:nth-child(2) .mod_kanbanlearning_card .mod_kanbanlearning_complete_card a" "css_element"
    And I wait until ".mod_kanbanlearning_column_container > .mod_kanbanlearning_column:last-child .mod_kanbanlearning_card" "css_element" exists
    And I click on ".mod_kanbanlearning_column_container > .mod_kanbanlearning_column:last-child .mod_kanbanlearning_card .mod_kanbanlearning_action_menu_trigger" "css_element"
    And I click on ".mod_kanbanlearning_approval_seal_menu [data-action='toggle_approval_seal']" "css_element"
    And I wait "1" seconds
    And I click on ".mod_kanbanlearning_approval_seal_menu [data-action='approval_seal'][data-seal='approved']" "css_element"
    And I am on the "Learning Kanban" "kanbanlearning activity" page logged in as student1
    Then I should see "✅" in the ".mod_kanbanlearning_approval_seal" "css_element"
    When I click on ".mod_kanbanlearning_column_container > .mod_kanbanlearning_column:last-child .mod_kanbanlearning_card .mod_kanbanlearning_action_menu_trigger" "css_element"
    Then ".mod_kanbanlearning_approval_seal_menu" "css_element" should not exist
