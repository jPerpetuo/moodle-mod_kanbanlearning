@mod @mod_kanbanlearning
Feature: Discuss card work through the board interface
  In order to clarify work in context
  As a teacher
  I need to send and see a discussion message without reloading the board

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
  Scenario: A teacher can send a discussion message without reloading the board
    Given I am on the "Learning Kanban" "kanbanlearning activity" page logged in as teacher1
    When I click on ".mod_kanbanlearning_column_container > .mod_kanbanlearning_column:nth-child(2) .mod_kanbanlearning_addcard_link" "css_element"
    And I wait until ".mod_kanbanlearning_column_container > .mod_kanbanlearning_column:nth-child(2) .mod_kanbanlearning_card" "css_element" exists
    And I click on ".mod_kanbanlearning_column_container > .mod_kanbanlearning_column:nth-child(2) .mod_kanbanlearning_card .mod_kanbanlearning_discussion_trigger" "css_element"
    And I wait until ".mod_kanbanlearning_discussion_modal.show:not(.mod_kanbanlearning_loading)" "css_element" exists
    And I set the field with xpath "//div[contains(@class, 'mod_kanbanlearning_discussion_modal') and contains(@class, 'show')]//textarea[contains(@class, 'mod_kanbanlearning_discussion_input')]" to "Discussion regression message"
    And I click on ".mod_kanbanlearning_discussion_modal.show .mod_kanbanlearning_discussion_send" "css_element"
    And I wait until "//div[contains(@class, 'mod_kanbanlearning_discussion_modal') and contains(@class, 'show')]//ul[contains(@class, 'mod_kanbanlearning_discussion_messages')]/li[contains(., 'Discussion regression message')]" "xpath_element" exists
    Then I should see "Terry Teacher" in the ".mod_kanbanlearning_discussion_modal.show .mod_kanbanlearning_discussion_messages" "css_element"
