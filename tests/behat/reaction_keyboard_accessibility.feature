@mod @mod_kanbanlearning
Feature: Keyboard access to approval reactions
  In order to use feedback controls without a mouse
  As a teacher
  I need to open and choose a reaction with the keyboard

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
      | activity       | kanbanlearning       |
      | course         | C1                   |
      | idnumber       | kanbanlearning1      |
      | name           | Learning Kanban      |
      | section        | 1                    |
      | approval_seals | 1                    |
      | boardmode      | 0                    |

  @javascript
  Scenario: A teacher can open and select a reaction using the keyboard
    Given I am on the "Learning Kanban" "kanbanlearning activity" page logged in as teacher1
    When I click on ".mod_kanbanlearning_column_container > .mod_kanbanlearning_column:nth-child(2) .mod_kanbanlearning_addcard_link" "css_element"
    And I wait until ".mod_kanbanlearning_column_container > .mod_kanbanlearning_column:nth-child(2) .mod_kanbanlearning_card" "css_element" exists
    And I click on ".mod_kanbanlearning_column_container > .mod_kanbanlearning_column:nth-child(2) .mod_kanbanlearning_card .mod_kanbanlearning_action_menu_trigger" "css_element"
    And I click on ".mod_kanbanlearning_column_container > .mod_kanbanlearning_column:nth-child(2) .mod_kanbanlearning_card .mod_kanbanlearning_complete_card a" "css_element"
    And I wait until ".mod_kanbanlearning_column_container > .mod_kanbanlearning_column:last-child .mod_kanbanlearning_card" "css_element" exists
    And I click on ".mod_kanbanlearning_column_container > .mod_kanbanlearning_column:last-child .mod_kanbanlearning_card .mod_kanbanlearning_action_menu_trigger" "css_element"
    And I focus the approval reaction control on the completed Kanban card
    And I press the enter key
    Then the focused element is ".mod_kanbanlearning_column_container > .mod_kanbanlearning_column:last-child .mod_kanbanlearning_card [data-action='toggle_approval_seal']" "css_element"
    When I press the tab key
    Then the focused element is ".mod_kanbanlearning_column_container > .mod_kanbanlearning_column:last-child .mod_kanbanlearning_card [data-action='approval_seal'][data-seal='approved']" "css_element"
    When I press the enter key
    And I wait until ".mod_kanbanlearning_column_container > .mod_kanbanlearning_column:last-child .mod_kanbanlearning_card .mod_kanbanlearning_approval_seal" "css_element" exists
    Then I should see "✅" in the ".mod_kanbanlearning_column_container > .mod_kanbanlearning_column:last-child .mod_kanbanlearning_card .mod_kanbanlearning_approval_seal" "css_element"
    And the focused element is ".mod_kanbanlearning_column_container > .mod_kanbanlearning_column:last-child .mod_kanbanlearning_card [data-action='toggle_approval_seal']" "css_element"
  @javascript
  Scenario: The reaction picker reduces motion when the browser requests it
    Given I am on the "Learning Kanban" "kanbanlearning activity" page logged in as teacher1
    When I click on ".mod_kanbanlearning_column_container > .mod_kanbanlearning_column:nth-child(2) .mod_kanbanlearning_addcard_link" "css_element"
    And I wait until ".mod_kanbanlearning_column_container > .mod_kanbanlearning_column:nth-child(2) .mod_kanbanlearning_card" "css_element" exists
    And I click on ".mod_kanbanlearning_column_container > .mod_kanbanlearning_column:nth-child(2) .mod_kanbanlearning_card .mod_kanbanlearning_action_menu_trigger" "css_element"
    And I click on ".mod_kanbanlearning_column_container > .mod_kanbanlearning_column:nth-child(2) .mod_kanbanlearning_complete_card a" "css_element"
    And I wait until ".mod_kanbanlearning_column_container > .mod_kanbanlearning_column:last-child .mod_kanbanlearning_card" "css_element" exists
    And I click on ".mod_kanbanlearning_column_container > .mod_kanbanlearning_column:last-child .mod_kanbanlearning_card .mod_kanbanlearning_action_menu_trigger" "css_element"
    When I open the approval reaction picker with reduced motion enabled
    Then the approval reaction picker uses opacity-only reduced motion
