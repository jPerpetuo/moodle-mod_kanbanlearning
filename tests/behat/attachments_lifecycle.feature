@mod @mod_kanbanlearning
Feature: Manage card attachments through the board interface
  In order to share supporting files with a card
  As a teacher
  I need to add and remove attachments without reloading the board

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

  @_file_upload @javascript
  Scenario: A teacher can add and remove a card attachment without reloading
    Given I am on the "Learning Kanban" "kanbanlearning activity" page logged in as teacher1
    When I click on ".mod_kanbanlearning_column_container > .mod_kanbanlearning_column:nth-child(2) .mod_kanbanlearning_addcard_link" "css_element"
    And I wait until ".mod_kanbanlearning_column_container > .mod_kanbanlearning_column:nth-child(2) .mod_kanbanlearning_card" "css_element" exists
    And I click on ".mod_kanbanlearning_column_container > .mod_kanbanlearning_column:nth-child(2) .mod_kanbanlearning_card:nth-child(2) .mod_kanbanlearning_card_title .quickeditlink" "css_element"
    And I set the field with xpath "//textarea[contains(@class, 'mod_kanbanlearning_card_title_editor')]" to "Kanban attachment regression card"
    And I click on ".mod_kanbanlearning_column_container > .mod_kanbanlearning_column:nth-child(2) .mod_kanbanlearning_card:nth-child(2) .mod_kanbanlearning_action_menu_trigger" "css_element"
    And I click on ".mod_kanbanlearning_column_container > .mod_kanbanlearning_column:nth-child(2) .mod_kanbanlearning_card:nth-child(2) [data-action='edit_details']" "css_element"
    And I wait until ".modal.show [data-fieldtype='filemanager']" "css_element" exists
    And I upload "lib/tests/fixtures/empty.txt" file to "Attachments" filemanager
    And I press "Save changes"
    And I wait until ".modal.show" "css_element" does not exist
    And I wait until "//li[contains(concat(' ', normalize-space(@class), ' '), ' mod_kanbanlearning_card ')][.//a[normalize-space(.)='Kanban attachment regression card'] and contains(concat(' ', normalize-space(@class), ' '), ' mod_kanbanlearning_hasattachment ')]" "xpath_element" exists
    And I click on ".mod_kanbanlearning_column_container > .mod_kanbanlearning_column:nth-child(2) .mod_kanbanlearning_card:nth-child(2) .mod_kanbanlearning_detail_trigger" "css_element"
    Then I should see "empty.txt" in the ".modal.show .mod_kanbanlearning_description_modal" "css_element"
    When I press the escape key
    And I click on ".mod_kanbanlearning_column_container > .mod_kanbanlearning_column:nth-child(2) .mod_kanbanlearning_card:nth-child(2) .mod_kanbanlearning_action_menu_trigger" "css_element"
    And I click on ".mod_kanbanlearning_column_container > .mod_kanbanlearning_column:nth-child(2) .mod_kanbanlearning_card:nth-child(2) [data-action='edit_details']" "css_element"
    And I wait until ".modal.show [data-fieldtype='filemanager']" "css_element" exists
    And I delete "empty.txt" from "Attachments" filemanager
    And I press "Save changes"
    And I wait until ".modal.show" "css_element" does not exist
    And I wait until "//li[contains(concat(' ', normalize-space(@class), ' '), ' mod_kanbanlearning_card ')][.//a[normalize-space(.)='Kanban attachment regression card'] and contains(concat(' ', normalize-space(@class), ' '), ' mod_kanbanlearning_hasattachment ')]" "xpath_element" does not exist
    And I click on ".mod_kanbanlearning_column_container > .mod_kanbanlearning_column:nth-child(2) .mod_kanbanlearning_card:nth-child(2) .mod_kanbanlearning_detail_trigger" "css_element"
    And I should not see "empty.txt" in the ".modal.show .mod_kanbanlearning_description_modal" "css_element"
