<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace mod_kanbanlearning;

/**
 * Tests for the activity callbacks in lib.php.
 *
 * @package     mod_kanbanlearning
 * @copyright   2026 CCEAD PUC-Rio
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      ::kanbanlearning_update_instance
 * @covers      ::kanbanlearning_delete_instance
 */
final class lib_test extends \advanced_testcase {
    /**
     * Updating an activity persists normalized group settings.
     *
     * @return void
     */
    public function test_update_instance_persists_normalized_group_settings(): void {
        global $DB;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $activity = $this->getDataGenerator()->create_module('kanbanlearning', ['course' => $course]);
        $data = (object) [
            'instance' => $activity->id,
            'name' => 'Updated Kanban Learning',
            'usenumbers' => 1,
            'boardgroups' => '17;23,17',
        ];

        $hadselectedgroups = array_key_exists('selectedboardgroups', $_POST);
        $originalselectedgroups = $_POST['selectedboardgroups'] ?? null;
        unset($_POST['selectedboardgroups']);
        try {
            $result = \kanbanlearning_update_instance($data);
        } finally {
            if ($hadselectedgroups) {
                $_POST['selectedboardgroups'] = $originalselectedgroups;
            } else {
                unset($_POST['selectedboardgroups']);
            }
        }

        $this->assertSame(1, $result);
        $stored = $DB->get_record('kanbanlearning', ['id' => $activity->id]);
        $this->assertSame('Updated Kanban Learning', $stored->name);
        $this->assertSame('17,23', $stored->boardgroups);
        $this->assertSame(17, (int) $stored->boardgroupid);
        $this->assertSame(1, (int) $stored->linknumbers);
    }

    /**
     * Deleting an activity removes its boards and their associated card data.
     *
     * @return void
     */
    public function test_delete_instance_removes_boards_and_associated_card_data(): void {
        global $DB, $USER;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $activity = $this->getDataGenerator()->create_module('kanbanlearning', ['course' => $course]);
        $boardid = (int) $DB->get_field(
            'kanbanlearning_board',
            'id',
            ['kanbanlearning_instance' => $activity->id],
            MUST_EXIST
        );
        $manager = new boardmanager($activity->cmid, $boardid);
        $columnid = (int) $DB->get_field(
            'kanbanlearning_column',
            'id',
            ['kanbanlearning_board' => $boardid],
            IGNORE_MULTIPLE
        );
        $cardid = $manager->add_card($columnid, 0, ['title' => 'Card to delete']);
        $DB->insert_record('kanbanlearning_comment', (object) [
            'kanbanlearning_card' => $cardid,
            'userid' => $USER->id,
            'content' => 'Comment to delete',
            'timecreated' => time(),
        ]);
        $DB->insert_record('kanbanlearning_assignee', (object) [
            'kanbanlearning_card' => $cardid,
            'userid' => $USER->id,
        ]);

        $this->assertTrue(\kanbanlearning_delete_instance($activity->id));

        $this->assertFalse($DB->record_exists('kanbanlearning', ['id' => $activity->id]));
        $this->assertFalse($DB->record_exists('kanbanlearning_board', ['id' => $boardid]));
        $this->assertFalse($DB->record_exists('kanbanlearning_column', ['kanbanlearning_board' => $boardid]));
        $this->assertFalse($DB->record_exists('kanbanlearning_card', ['id' => $cardid]));
        $this->assertFalse($DB->record_exists('kanbanlearning_comment', ['kanbanlearning_card' => $cardid]));
        $this->assertFalse($DB->record_exists('kanbanlearning_assignee', ['kanbanlearning_card' => $cardid]));
    }
}
