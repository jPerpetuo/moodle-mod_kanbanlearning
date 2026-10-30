@mod @mod_kanbanlearning
Feature: Move cards using drag and drop
  In order to update work quickly
  As a teacher
  I need to drag a card from one workflow column to another

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
  Scenario: A teacher can drag a card to another column without reloading
    Given I am on the "Learning Kanban" "kanbanlearning activity" page logged in as teacher1
    When I click on ".mod_kanbanlearning_column_container > .mod_kanbanlearning_column:nth-child(2) .mod_kanbanlearning_addcard_link" "css_element"
    And I wait until ".mod_kanbanlearning_column_container > .mod_kanbanlearning_column:nth-child(2) .mod_kanbanlearning_card" "css_element" exists
    And I click on ".mod_kanbanlearning_column_container > .mod_kanbanlearning_column:nth-child(2) .mod_kanbanlearning_card:nth-child(2) .mod_kanbanlearning_card_title .quickeditlink" "css_element"
    And I set the field with xpath "//textarea[contains(@class, 'mod_kanbanlearning_card_title_editor')]" to "Kanban drag and drop regression card"
    And I press the enter key
    And I click on ".mod_kanbanlearning_column_container > .mod_kanbanlearning_column:nth-child(2) .mod_kanbanlearning_card:nth-child(2) .mod_kanbanlearning_action_menu_trigger" "css_element"
    And I wait until "//li[contains(@class, 'mod_kanbanlearning_column')][2]//li[contains(@class, 'mod_kanbanlearning_card')][.//a[normalize-space(.)='Kanban drag and drop regression card']]" "xpath_element" exists
    When I drag the "Kanban drag and drop regression card" card to the bottom of the "Doing" column on the Kanban board
    Then I wait until "//li[contains(@class, 'mod_kanbanlearning_column')][3]//li[contains(@class, 'mod_kanbanlearning_card')][.//a[normalize-space(.)='Kanban drag and drop regression card']]" "xpath_element" exists
    And I should see "Kanban drag and drop regression card" in the "//li[contains(@class, 'mod_kanbanlearning_column')][3]" "xpath_element"
