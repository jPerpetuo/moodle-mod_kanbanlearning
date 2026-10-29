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
    When I move to ".mod_kanbanlearning_column [data-action='add_column']" "css_element"
    And I click on ".mod_kanbanlearning_column [data-action='add_column']" "css_element"
    And I wait until "//li[contains(@class, 'mod_kanbanlearning_column')][.//a[normalize-space(.)='New column']]" "xpath_element" exists
    And I click on "//li[contains(@class, 'mod_kanbanlearning_column')][.//a[normalize-space(.)='New column']]//button[contains(@class, 'mod_kanbanlearning_action_menu_trigger')]" "xpath_element"
    And I click on "//li[contains(@class, 'mod_kanbanlearning_column')][.//a[normalize-space(.)='New column']]//*[@data-action='edit_details']" "xpath_element"
    And I wait until ".modal.show input[name='title']" "css_element" exists
    And I set the field "Column title" to "Kanban source"
    And I click on ".modal.show input[name='dotcolor'][value='#3579DC']" "css_element"
    And I press "Save changes"
    Then I should see "Kanban source" in the "//li[contains(@class, 'mod_kanbanlearning_column')][.//a[normalize-space(.)='Kanban source']]" "xpath_element"
    And I wait until "//li[contains(@class, 'mod_kanbanlearning_column')][.//a[normalize-space(.)='Kanban source']]//span[contains(@class, 'mod_kanbanlearning_column_dot') and contains(@style, 'rgb(53, 121, 220)')]" "xpath_element" exists
    When I move to ".mod_kanbanlearning_column [data-action='add_column']" "css_element"
    And I click on ".mod_kanbanlearning_column [data-action='add_column']" "css_element"
    And I wait until "//li[contains(@class, 'mod_kanbanlearning_column')][.//a[normalize-space(.)='New column']]" "xpath_element" exists
    And I click on "//li[contains(@class, 'mod_kanbanlearning_column')][.//a[normalize-space(.)='New column']]//button[contains(@class, 'mod_kanbanlearning_action_menu_trigger')]" "xpath_element"
    And I click on "//li[contains(@class, 'mod_kanbanlearning_column')][.//a[normalize-space(.)='New column']]//*[@data-action='edit_details']" "xpath_element"
    And I wait until ".modal.show input[name='title']" "css_element" exists
    And I set the field "Column title" to "Kanban target"
    And I press "Save changes"
    Then I should see "Kanban target" in the "//li[contains(@class, 'mod_kanbanlearning_column')][.//a[normalize-space(.)='Kanban target']]" "xpath_element"
    When I click on "//li[contains(@class, 'mod_kanbanlearning_column')][.//a[normalize-space(.)='Kanban source']]//button[contains(@class, 'mod_kanbanlearning_action_menu_trigger')]" "xpath_element"
    And I click on "//li[contains(@class, 'mod_kanbanlearning_column')][.//a[normalize-space(.)='Kanban source']]//*[@data-action='lock_column']" "xpath_element"
    And I wait until "//li[contains(@class, 'mod_kanbanlearning_locked_column')][.//a[normalize-space(.)='Kanban source']]" "xpath_element" exists
    And I click on "//li[contains(@class, 'mod_kanbanlearning_column')][.//a[normalize-space(.)='Kanban source']]//button[contains(@class, 'mod_kanbanlearning_action_menu_trigger')]" "xpath_element"
    And I click on "//li[contains(@class, 'mod_kanbanlearning_column')][.//a[normalize-space(.)='Kanban source']]//*[@data-action='unlock_column']" "xpath_element"
    And I wait until "//li[contains(@class, 'mod_kanbanlearning_column') and not(contains(@class, 'mod_kanbanlearning_locked_column'))][.//a[normalize-space(.)='Kanban source']]" "xpath_element" exists
    And I drag the "Kanban target" column after the "Kanban source" column on the Kanban board
    And I wait until "//li[contains(@class, 'mod_kanbanlearning_column')][.//a[normalize-space(.)='Kanban source']]/following-sibling::li[contains(@class, 'mod_kanbanlearning_column')][1][.//a[normalize-space(.)='Kanban target']]" "xpath_element" exists
    And I click on "//li[contains(@class, 'mod_kanbanlearning_column')][.//a[normalize-space(.)='Kanban target']]//button[contains(@class, 'mod_kanbanlearning_action_menu_trigger')]" "xpath_element"
    And I click on "//li[contains(@class, 'mod_kanbanlearning_column')][.//a[normalize-space(.)='Kanban target']]//*[@data-action='delete_column']" "xpath_element"
    And I wait until ".modal.show .modal-footer .btn-primary" "css_element" exists
    And I click on ".modal.show .modal-footer .btn-primary" "css_element"
    And I wait until "//li[contains(@class, 'mod_kanbanlearning_column')][.//a[normalize-space(.)='Kanban target']]" "xpath_element" does not exist
    Then I should see "Kanban source" in the "//li[contains(@class, 'mod_kanbanlearning_column')][.//a[normalize-space(.)='Kanban source']]" "xpath_element"
