@mod @mod_kanbanlearning
Feature: Manage columns through the board interface
  In order to keep the workflow organized
  As a teacher
  I need to create, edit, reorder, lock and delete columns without reloading the board

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
  Scenario: A teacher can manage columns without reloading the board
    Given I am on the "Learning Kanban" "kanbanlearning activity" page logged in as teacher1
    When I click on ".mod_kanbanlearning_column:last-child [data-action='add_column']" "css_element"
    And I wait until ".mod_kanbanlearning_column:nth-of-type(5)" "css_element" exists
    And I click on ".mod_kanbanlearning_column:nth-of-type(5) .mod_kanbanlearning_action_menu_trigger" "css_element"
    And I click on ".mod_kanbanlearning_column:nth-of-type(5) [data-action='edit_details']" "css_element"
    And I wait until ".modal.show input[name='title']" "css_element" exists
    And I set the field "Column title" to "Kanban source"
    And I click on ".modal.show input[name='dotcolor'][value='#3579DC']" "css_element"
    And I press "Save changes"
    Then I should see "Kanban source" in the ".mod_kanbanlearning_column:nth-of-type(5) .mod_kanbanlearning_column_title" "css_element"
    And I wait until ".mod_kanbanlearning_column:nth-of-type(5) .mod_kanbanlearning_column_dot[style*='rgb(53, 121, 220)']" "css_element" exists
    When I click on ".mod_kanbanlearning_column:last-child [data-action='add_column']" "css_element"
    And I wait until ".mod_kanbanlearning_column:nth-of-type(6)" "css_element" exists
    And I click on ".mod_kanbanlearning_column:nth-of-type(6) .mod_kanbanlearning_action_menu_trigger" "css_element"
    And I click on ".mod_kanbanlearning_column:nth-of-type(6) [data-action='edit_details']" "css_element"
    And I wait until ".modal.show input[name='title']" "css_element" exists
    And I set the field "Column title" to "Kanban target"
    And I press "Save changes"
    Then I should see "Kanban target" in the ".mod_kanbanlearning_column:nth-of-type(6) .mod_kanbanlearning_column_title" "css_element"
    When I click on ".mod_kanbanlearning_column:nth-of-type(5) .mod_kanbanlearning_action_menu_trigger" "css_element"
    And I click on ".mod_kanbanlearning_column:nth-of-type(5) [data-action='lock_column']" "css_element"
    And I wait until ".mod_kanbanlearning_column:nth-of-type(5).mod_kanbanlearning_locked_column" "css_element" exists
    And I click on ".mod_kanbanlearning_column:nth-of-type(5) .mod_kanbanlearning_action_menu_trigger" "css_element"
    And I click on ".mod_kanbanlearning_column:nth-of-type(5) [data-action='unlock_column']" "css_element"
    And I wait until ".mod_kanbanlearning_column:nth-of-type(5):not(.mod_kanbanlearning_locked_column)" "css_element" exists
    And I drag "//li[contains(@class, 'mod_kanbanlearning_column')][.//a[normalize-space(.)='Kanban source']]" "xpath_element" and I drop it in "//li[contains(@class, 'mod_kanbanlearning_column')][.//a[normalize-space(.)='Kanban target']]" "xpath_element"
    And I wait until "//ul[contains(@class, 'mod_kanbanlearning_column_container')]/li[contains(@class, 'mod_kanbanlearning_column')][last()][.//a[normalize-space(.)='Kanban source']]" "xpath_element" exists
    And I click on "//li[contains(@class, 'mod_kanbanlearning_column')][.//a[normalize-space(.)='Kanban target']]//button[contains(@class, 'mod_kanbanlearning_action_menu_trigger')]" "xpath_element"
    And I click on "//li[contains(@class, 'mod_kanbanlearning_column')][.//a[normalize-space(.)='Kanban target']]//*[@data-action='delete_column']" "xpath_element"
    And I wait until ".modal.show .modal-footer .btn-primary" "css_element" exists
    And I click on ".modal.show .modal-footer .btn-primary" "css_element"
    And I wait until "//li[contains(@class, 'mod_kanbanlearning_column')][.//a[normalize-space(.)='Kanban target']]" "xpath_element" does not exist
    Then I should see "Kanban source" in the ".mod_kanbanlearning_column:last-child .mod_kanbanlearning_column_title" "css_element"