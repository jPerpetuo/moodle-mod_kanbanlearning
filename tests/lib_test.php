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
    /**
     * Resetting personal boards preserves the shared and group boards.
     *
     * @return void
     * @covers ::kanbanlearning_reset_userdata
     */
    public function test_reset_userdata_removes_only_personal_boards_when_selected(): void {
        global $DB;

        $fixture = $this->create_reset_fixture();
        $status = \\kanbanlearning_reset_userdata((object) [
            'courseid' => $fixture['course']->id,
            'reset_kanbanlearning_personal' => 1,
        ]);

        $this->assertCount(1, $status);
        $this->assertFalse($DB->record_exists('kanbanlearning_board', ['id' => $fixture['personalboardid']]));
        $this->assertFalse($DB->record_exists('kanbanlearning_card', ['id' => $fixture['personalcardid']]));
        $this->assertTrue($DB->record_exists('kanbanlearning_board', ['id' => $fixture['sharedboardid']]));
        $this->assertTrue($DB->record_exists('kanbanlearning_board', ['id' => $fixture['groupboardid']]));
        $this->assertTrue($DB->record_exists('kanbanlearning_board', ['id' => $fixture['templateid']]));
        $this->assertTrue($DB->record_exists('kanbanlearning_card', ['id' => $fixture['sharedcardid']]));
        $this->assertTrue($DB->record_exists('kanbanlearning_card', ['id' => $fixture['groupcardid']]));
    }

    /**
     * Resetting group boards preserves personal and shared boards.
     *
     * @return void
     * @covers ::kanbanlearning_reset_userdata
     */
    public function test_reset_userdata_removes_only_group_boards_when_selected(): void {
        global $DB;

        $fixture = $this->create_reset_fixture();
        $status = \\kanbanlearning_reset_userdata((object) [
            'courseid' => $fixture['course']->id,
            'reset_kanbanlearning_group' => 1,
        ]);

        $this->assertCount(1, $status);
        $this->assertFalse($DB->record_exists('kanbanlearning_board', ['id' => $fixture['groupboardid']]));
        $this->assertFalse($DB->record_exists('kanbanlearning_card', ['id' => $fixture['groupcardid']]));
        $this->assertTrue($DB->record_exists('kanbanlearning_board', ['id' => $fixture['sharedboardid']]));
        $this->assertTrue($DB->record_exists('kanbanlearning_board', ['id' => $fixture['personalboardid']]));
        $this->assertTrue($DB->record_exists('kanbanlearning_board', ['id' => $fixture['templateid']]));
        $this->assertTrue($DB->record_exists('kanbanlearning_card', ['id' => $fixture['sharedcardid']]));
        $this->assertTrue($DB->record_exists('kanbanlearning_card', ['id' => $fixture['personalcardid']]));
    }

    /**
     * Resetting all activity boards removes shared, personal and group boards but keeps templates.
     *
     * @return void
     * @covers ::kanbanlearning_reset_userdata
     */
    public function test_reset_userdata_removes_all_non_template_boards_for_course(): void {
        global $DB;

        $fixture = $this->create_reset_fixture();
        $othercourse = $this->getDataGenerator()->create_course();
        $otheractivity = $this->getDataGenerator()->create_module('kanbanlearning', ['course' => $othercourse]);
        $otherboardid = (int) $DB->get_field(
            'kanbanlearning_board',
            'id',
            ['kanbanlearning_instance' => $otheractivity->id],
            MUST_EXIST
        );

        $status = \\kanbanlearning_reset_userdata((object) [
            'courseid' => $fixture['course']->id,
            'reset_kanbanlearning' => 1,
        ]);

        $this->assertCount(3, $status);
        foreach (['sharedboardid', 'personalboardid', 'groupboardid'] as $key) {
            $this->assertFalse($DB->record_exists('kanbanlearning_board', ['id' => $fixture[$key]]));
        }
        foreach (['sharedcardid', 'personalcardid', 'groupcardid'] as $key) {
            $this->assertFalse($DB->record_exists('kanbanlearning_card', ['id' => $fixture[$key]]));
        }
        $this->assertTrue($DB->record_exists('kanbanlearning_board', ['id' => $fixture['templateid']]));
        $this->assertTrue($DB->record_exists('kanbanlearning_board', ['id' => $otherboardid]));
    }

    /**
     * Resetting a course with no Kanban options selected leaves its boards unchanged.
     *
     * @return void
     * @covers ::kanbanlearning_reset_userdata
     */
    public function test_reset_userdata_with_no_options_preserves_boards(): void {
        global $DB;

        $fixture = $this->create_reset_fixture();
        $status = \\kanbanlearning_reset_userdata((object) [
            'courseid' => $fixture['course']->id,
        ]);

        $this->assertSame([], $status);
        foreach (['sharedboardid', 'personalboardid', 'groupboardid', 'templateid'] as $key) {
            $this->assertTrue($DB->record_exists('kanbanlearning_board', ['id' => $fixture[$key]]));
        }
        foreach (['sharedcardid', 'personalcardid', 'groupcardid'] as $key) {
            $this->assertTrue($DB->record_exists('kanbanlearning_card', ['id' => $fixture[$key]]));
        }
    }

    /**
     * Create shared, personal and group boards, their cards, and a structural template.
     *
     * @return array
     */
    private function create_reset_fixture(): array {
        global $DB;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $activity = $this->getDataGenerator()->create_module('kanbanlearning', ['course' => $course]);
        $manager = new boardmanager($activity->cmid);
        $sharedboardid = (int) $DB->get_field(
            'kanbanlearning_board',
            'id',
            ['kanbanlearning_instance' => $activity->id, 'userid' => 0, 'groupid' => 0, 'template' => 0],
            MUST_EXIST
        );

        $user = $this->getDataGenerator()->create_user();
        $personalboardid = $manager->create_user_board($user->id);
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $groupboardid = $manager->create_group_board($group->id);
        $manager->load_board($sharedboardid);
        $templateid = $manager->create_template();

        $cardids = [];
        foreach ([
            'shared' => $sharedboardid,
            'personal' => $personalboardid,
            'group' => $groupboardid,
        ] as $scope => $boardid) {
            $manager->load_board($boardid);
            $columnid = (int) $DB->get_field(
                'kanbanlearning_column',
                'id',
                ['kanbanlearning_board' => $boardid],
                IGNORE_MULTIPLE
            );
            $cardids[$scope] = $manager->add_card($columnid, 0, ['title' => $scope . ' reset test card']);
        }

        return [
            'course' => $course,
            'sharedboardid' => $sharedboardid,
            'personalboardid' => $personalboardid,
            'groupboardid' => $groupboardid,
            'templateid' => $templateid,
            'sharedcardid' => $cardids['shared'],
            'personalcardid' => $cardids['personal'],
            'groupcardid' => $cardids['group'],
        ];
    }

}
