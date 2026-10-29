@mod @mod_kanbanlearning
Feature: Review card history through the board interface
  In order to understand how work changed
  As a teacher
  I need to view the card history without reloading the board

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
      | history  | 1               |

  @javascript
  Scenario: A teacher can see a card title change in its history without reloading
    Given I am on the "Learning Kanban" "kanbanlearning activity" page logged in as teacher1
    When I click on ".mod_kanbanlearning_column_container > .mod_kanbanlearning_column:nth-child(2) .mod_kanbanlearning_addcard_link" "css_element"
    And I wait until ".mod_kanbanlearning_column_container > .mod_kanbanlearning_column:nth-child(2) .mod_kanbanlearning_card" "css_element" exists
    And I click on ".mod_kanbanlearning_column_container > .mod_kanbanlearning_column:nth-child(2) .mod_kanbanlearning_card:nth-child(2) .mod_kanbanlearning_card_title .quickeditlink" "css_element"
    And I set the field with xpath "//textarea[contains(@class, 'mod_kanbanlearning_card_title_editor')]" to "Kanban history regression card"
    And I click on ".mod_kanbanlearning_column_container > .mod_kanbanlearning_column:nth-child(2) .mod_kanbanlearning_card:nth-child(2) .mod_kanbanlearning_action_menu_trigger" "css_element"
    And I wait until "//li[contains(@class, 'mod_kanbanlearning_card')][.//a[normalize-space(.)='Kanban history regression card']]" "xpath_element" exists
    And I click on "//li[contains(@class, 'mod_kanbanlearning_card')][.//a[normalize-space(.)='Kanban history regression card']]//*[@data-action='show_history']" "xpath_element"
    And I wait until ".mod_kanbanlearning_history_modal.show" "css_element" exists
    And I wait until ".mod_kanbanlearning_history_modal.show:not(.mod_kanbanlearning_loading)" "css_element" exists
    Then I should see "Kanban history regression card" in the ".mod_kanbanlearning_history_modal.show .mod_kanbanlearning_history_items" "css_element"
