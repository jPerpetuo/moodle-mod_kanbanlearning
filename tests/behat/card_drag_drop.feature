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
    Given the Kanban activity has a card titled "Kanban drag and drop regression card" in its second column
    And I am on the "Learning Kanban" "kanbanlearning activity" page logged in as teacher1
    And I wait until "//li[contains(@class, 'mod_kanbanlearning_column')][2]//li[contains(@class, 'mod_kanbanlearning_card')][.//a[normalize-space(.)='Kanban drag and drop regression card']]" "xpath_element" exists
    When I drag the "Kanban drag and drop regression card" card to the bottom of the "Doing" column on the Kanban board
    Then I wait until "//li[contains(@class, 'mod_kanbanlearning_column')][3]//li[contains(@class, 'mod_kanbanlearning_card')][.//a[normalize-space(.)='Kanban drag and drop regression card']]" "xpath_element" exists
    And I should see "Kanban drag and drop regression card" in the "//li[contains(@class, 'mod_kanbanlearning_column')][3]" "xpath_element"
