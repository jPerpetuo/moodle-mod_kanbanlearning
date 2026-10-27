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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

/**
 * Tests for Kanban activity backup and restore.
 *
 * @package     mod_kanbanlearning
 * @copyright   2026 CCEAD PUC-Rio
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class backup_test extends \advanced_testcase {
    /**
     * Backing up without user data retains board structure as a template and excludes cards.
     *
     * @return void
     * @covers \backup_kanbanlearning_activity_structure_step::define_structure
     * @covers \backup_kanbanlearning_activity_structure_step::get_structure_source_board_id
     * @covers \restore_kanbanlearning_activity_structure_step::process_board
     * @covers \restore_kanbanlearning_activity_structure_step::process_column
     * @covers \restore_kanbanlearning_activity_structure_step::process_card
     * @covers \restore_kanbanlearning_activity_structure_step::after_execute
     */
    public function test_restore_without_user_data_keeps_structure_and_excludes_cards(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $fixture = $this->create_backup_fixture();

        $this->backup_and_restore_activity($fixture['cmid'], $fixture['destinationcourse']->id, ['users' => false]);

        $activity = $DB->get_record('kanbanlearning', [
            'course' => $fixture['destinationcourse']->id,
            'name' => 'Backup restore fixture',
        ], '*', MUST_EXIST);
        $boards = $DB->get_records('kanbanlearning_board', ['kanbanlearning_instance' => $activity->id]);
        $this->assertCount(1, $boards);

        $board = reset($boards);
        $this->assertSame(1, (int) $board->template);
        $this->assertSame(0, (int) $board->userid);
        $this->assertSame(0, (int) $board->groupid);
        $this->assertSame(1, (int) $board->locked);

        $columns = $DB->get_records('kanbanlearning_column', ['kanbanlearning_board' => $board->id]);
        $this->assertCount($fixture['columncount'], $columns);
        $column = $DB->get_record('kanbanlearning_column', [
            'kanbanlearning_board' => $board->id,
            'title' => 'Restored structure column',
        ], '*', MUST_EXIST);
        foreach ($columns as $restoredcolumn) {
            $this->assertSame('', $restoredcolumn->sequence);
        }
        $this->assertSame('Restored structure column', $column->title);
        $this->assertFalse($DB->record_exists('kanbanlearning_card', ['kanbanlearning_board' => $board->id]));
    }

    /**
     * Backing up with user data preserves cards, assignments and discussion comments.
     *
     * @return void
     * @covers \backup_kanbanlearning_activity_structure_step::define_structure
     * @covers \restore_kanbanlearning_activity_structure_step::process_board
     * @covers \restore_kanbanlearning_activity_structure_step::process_column
     * @covers \restore_kanbanlearning_activity_structure_step::process_card
     * @covers \restore_kanbanlearning_activity_structure_step::process_assignee
     * @covers \restore_kanbanlearning_activity_structure_step::process_discussion_comment
     * @covers \restore_kanbanlearning_activity_structure_step::after_execute
     */
    public function test_restore_with_user_data_preserves_cards_and_related_records(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $fixture = $this->create_backup_fixture();

        $this->backup_and_restore_activity($fixture['cmid'], $fixture['destinationcourse']->id, ['users' => true]);

        $activity = $DB->get_record('kanbanlearning', [
            'course' => $fixture['destinationcourse']->id,
            'name' => 'Backup restore fixture',
        ], '*', MUST_EXIST);
        $boards = $DB->get_records('kanbanlearning_board', ['kanbanlearning_instance' => $activity->id]);
        $this->assertCount(1, $boards);

        $board = reset($boards);
        $this->assertSame(0, (int) $board->template);
        $this->assertSame(1, (int) $board->locked);
        $columns = $DB->get_records('kanbanlearning_column', ['kanbanlearning_board' => $board->id]);
        $this->assertCount($fixture['columncount'], $columns);
        $column = $DB->get_record('kanbanlearning_column', [
            'kanbanlearning_board' => $board->id,
            'title' => 'Restored structure column',
        ], '*', MUST_EXIST);
        $this->assertSame('Restored structure column', $column->title);

        $cards = $DB->get_records('kanbanlearning_card', ['kanbanlearning_board' => $board->id]);
        $this->assertCount(1, $cards);
        $card = reset($cards);
        $this->assertSame('Backup card', $card->title);
        $this->assertSame('Card description', $card->description);
        $this->assertSame((string) $card->id, $column->sequence);

        $assignee = $DB->get_record('kanbanlearning_assignee', [
            'kanbanlearning_card' => $card->id,
        ], '*', MUST_EXIST);
        $comment = $DB->get_record('kanbanlearning_comment', [
            'kanbanlearning_card' => $card->id,
        ], '*', MUST_EXIST);
        $this->assertSame('Backup discussion comment', $comment->content);
        $this->assertSame($assignee->userid, $comment->userid);
        $this->assertGreaterThan(0, (int) $assignee->userid);
        $this->assertTrue($DB->record_exists('user', ['id' => $assignee->userid]));
    }

    /**
     * Restoring without user data preserves selected group settings when groups are included.
     *
     * @return void
     * @covers \restore_kanbanlearning_activity_structure_step::process_kanbanlearning
     * @covers \backup_kanbanlearning_activity_structure_step::get_structure_source_board_id
     * @covers \restore_kanbanlearning_activity_structure_step::process_board
     * @covers \restore_kanbanlearning_activity_structure_step::process_column
     */
    public function test_restore_group_structure_and_selection_with_groups_included(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $fixture = $this->create_group_backup_fixture();

        $this->backup_and_restore_activity($fixture['cmid'], $fixture['destinationcourse']->id, ['users' => false, 'groups' => true]);

        $activity = $DB->get_record('kanbanlearning', [
            'course' => $fixture['destinationcourse']->id,
            'name' => 'Group backup restore fixture',
        ], '*', MUST_EXIST);
        $groups = groups_get_all_groups($fixture['destinationcourse']->id);
        $this->assertCount(1, $groups);
        $restoredgroup = reset($groups);
        $this->assertSame(\mod_kanbanlearning\constants::MOD_KANBANLEARNING_BOARDMODE_GROUP, (int) $activity->boardmode);
        $this->assertSame((int) $restoredgroup->id, (int) $activity->boardgroupid);
        $this->assertSame((string) $restoredgroup->id, (string) $activity->boardgroups);

        $boards = $DB->get_records('kanbanlearning_board', ['kanbanlearning_instance' => $activity->id]);
        $this->assertCount(1, $boards);
        $board = reset($boards);
        $this->assertSame(1, (int) $board->template);
        $this->assertSame(0, (int) $board->groupid);
        $this->assertTrue($DB->record_exists('kanbanlearning_column', [
            'kanbanlearning_board' => $board->id,
            'title' => 'Selected group structure',
        ]));
        $this->assertFalse($DB->record_exists('kanbanlearning_card', ['kanbanlearning_board' => $board->id]));
    }

    /**
     * Restoring without group data clears selected groups and falls back to a shared board.
     *
     * @return void
     * @covers \restore_kanbanlearning_activity_structure_step::process_kanbanlearning
     * @covers \backup_kanbanlearning_activity_structure_step::get_structure_source_board_id
     * @covers \restore_kanbanlearning_activity_structure_step::process_board
     */
    public function test_restore_group_structure_without_groups_falls_back_to_shared_board(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $fixture = $this->create_group_backup_fixture();

        $this->backup_and_restore_activity($fixture['cmid'], $fixture['destinationcourse']->id, ['users' => false, 'groups' => false]);

        $activity = $DB->get_record('kanbanlearning', [
            'course' => $fixture['destinationcourse']->id,
            'name' => 'Group backup restore fixture',
        ], '*', MUST_EXIST);
        $this->assertSame(\mod_kanbanlearning\constants::MOD_KANBANLEARNING_BOARDMODE_SHARED, (int) $activity->boardmode);
        $this->assertSame(0, (int) $activity->boardgroupid);
        $this->assertSame('', (string) $activity->boardgroups);
        $this->assertEmpty(groups_get_all_groups($fixture['destinationcourse']->id));

        $boards = $DB->get_records('kanbanlearning_board', ['kanbanlearning_instance' => $activity->id]);
        $this->assertCount(1, $boards);
        $board = reset($boards);
        $this->assertSame(1, (int) $board->template);
        $this->assertSame(0, (int) $board->groupid);
        $this->assertTrue($DB->record_exists('kanbanlearning_column', [
            'kanbanlearning_board' => $board->id,
            'title' => 'Selected group structure',
        ]));
        $this->assertFalse($DB->record_exists('kanbanlearning_card', ['kanbanlearning_board' => $board->id]));
    }

    /**
     * Create a group-mode activity with a selected group board containing custom structure.
     *
     * @return array
     */
    private function create_group_backup_fixture(): array {
        global $DB;

        $sourcecourse = $this->getDataGenerator()->create_course();
        $destinationcourse = $this->getDataGenerator()->create_course();
        $group = $this->getDataGenerator()->create_group([
            'courseid' => $sourcecourse->id,
            'name' => 'Selected project group',
        ]);
        $activity = $this->getDataGenerator()->create_module('kanbanlearning', [
            'course' => $sourcecourse,
            'name' => 'Group backup restore fixture',
        ]);
        $DB->update_record('kanbanlearning', [
            'id' => $activity->id,
            'boardmode' => \mod_kanbanlearning\constants::MOD_KANBANLEARNING_BOARDMODE_GROUP,
            'boardgroupid' => $group->id,
            'boardgroups' => (string) $group->id,
        ]);

        $sourceboard = $DB->get_record('kanbanlearning_board', [
            'kanbanlearning_instance' => $activity->id,
            'userid' => 0,
            'groupid' => 0,
            'template' => 0,
        ], '*', MUST_EXIST);
        $groupboard = clone $sourceboard;
        unset($groupboard->id);
        $groupboard->groupid = $group->id;
        $groupboard->sequence = '';
        $groupboard->id = $DB->insert_record('kanbanlearning_board', $groupboard);

        $sourcecolumns = $DB->get_records('kanbanlearning_column', ['kanbanlearning_board' => $sourceboard->id]);
        $columnids = [];
        foreach ($sourcecolumns as $sourcecolumn) {
            $column = clone $sourcecolumn;
            unset($column->id);
            $column->kanbanlearning_board = $groupboard->id;
            $column->sequence = '';
            $column->id = $DB->insert_record('kanbanlearning_column', $column);
            $columnids[$sourcecolumn->id] = $column->id;
        }
        $firstcolumnid = reset($columnids);
        $DB->set_field('kanbanlearning_column', 'title', 'Selected group structure', ['id' => $firstcolumnid]);
        $groupboard->sequence = implode(',', array_intersect_key($columnids, array_flip(explode(',', $sourceboard->sequence))));
        $DB->update_record('kanbanlearning_board', [
            'id' => $groupboard->id,
            'sequence' => $groupboard->sequence,
        ]);

        return [
            'cmid' => $activity->cmid,
            'destinationcourse' => $destinationcourse,
        ];
    }

    /**
     * Create a source activity with custom structure, a card and user-related records.
     *
     * @return array
     */
    private function create_backup_fixture(): array {
        global $DB;

        $sourcecourse = $this->getDataGenerator()->create_course();
        $destinationcourse = $this->getDataGenerator()->create_course();
        $activity = $this->getDataGenerator()->create_module('kanbanlearning', [
            'course' => $sourcecourse,
            'name' => 'Backup restore fixture',
        ]);
        $boardid = (int) $DB->get_field(
            'kanbanlearning_board',
            'id',
            ['kanbanlearning_instance' => $activity->id, 'userid' => 0, 'groupid' => 0, 'template' => 0],
            MUST_EXIST
        );
        $DB->set_field('kanbanlearning_board', 'locked', 1, ['id' => $boardid]);
        $manager = new boardmanager($activity->cmid, $boardid);
        $columnid = (int) $DB->get_field_sql(
            'SELECT id FROM {kanbanlearning_column}
              WHERE kanbanlearning_board = :boardid
           ORDER BY sequence ASC, id ASC',
            ['boardid' => $boardid],
            IGNORE_MULTIPLE
        );
        $columncount = $DB->count_records('kanbanlearning_column', ['kanbanlearning_board' => $boardid]);
        $DB->set_field('kanbanlearning_column', 'title', 'Restored structure column', ['id' => $columnid]);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $sourcecourse->id);
        $cardid = $manager->add_card($columnid, 0, [
            'title' => 'Backup card',
            'description' => 'Card description',
        ]);
        $DB->insert_record('kanbanlearning_assignee', (object) [
            'kanbanlearning_card' => $cardid,
            'userid' => $user->id,
        ]);
        $DB->insert_record('kanbanlearning_comment', (object) [
            'kanbanlearning_card' => $cardid,
            'userid' => $user->id,
            'content' => 'Backup discussion comment',
            'timecreated' => time(),
        ]);

        return [
            'cmid' => $activity->cmid,
            'columncount' => $columncount,
            'userid' => $user->id,
            'destinationcourse' => $destinationcourse,
        ];
    }

    /**
     * Run an activity backup and restore with selected backup options.
     *
     * @param int $cmid Source course module id.
     * @param int $destinationcourseid Destination course id.
     * @param array $options Backup options. Supported keys are users and groups.
     * @return void
     */
    private function backup_and_restore_activity(int $cmid, int $destinationcourseid, array $options = []): void {
        $withuserinfo = (bool)($options['users'] ?? false);
        $includegroups = (bool)($options['groups'] ?? true);

        global $USER;

        $backup = new \backup_controller(
            \backup::TYPE_1ACTIVITY,
            $cmid,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id
        );
        try {
            $backup->get_plan()->get_setting('users')->set_value($withuserinfo);
            $backup->get_plan()->get_setting('groups')->set_value($includegroups);
            $backup->execute_plan();
            $backupid = $backup->get_backupid();
            $backupfile = $backup->get_results()['backup_destination'];
            $tempdir = make_temp_directory('backup/' . $backupid);
            $packer = get_file_packer('application/vnd.moodle.backup');
            $packer->extract_to_pathname($backupfile, $tempdir);
        } finally {
            $backup->destroy();
        }

        $restore = new \restore_controller(
            $backupid,
            $destinationcourseid,
            \backup::INTERACTIVE_NO,
            \backup::MODE_SAMESITE,
            $USER->id,
            \backup::TARGET_EXISTING_ADDING
        );
        try {
            $restore->get_plan()->get_setting('groups')->set_value($includegroups);
            $restore->execute_precheck();
            $restore->execute_plan();
        } finally {
            $restore->destroy();
        }
    }
}
