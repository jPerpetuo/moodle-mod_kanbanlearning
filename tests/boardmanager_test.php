<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace mod_kanbanlearning;

use context_course;

/**
 * Unit test for mod_kanbanlearning
 *
 * @package     mod_kanbanlearning
 * @copyright   2023-2024 ISB Bayern
 * @author      Stefan Hanauska <stefan.hanauska@csg-in.de>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \mod_kanbanlearning\boardmanager
 */
final class boardmanager_test extends \advanced_testcase {
    /** @var \stdClass The course used for testing */
    private $course;
    /** @var \stdClass The kanbanlearning used for testing */
    private $kanbanlearning;
    /** @var array The users used for testing */
    private $users;

    /**
     * Prepare testing environment
     */
    public function setUp(): void {
        global $DB;

        parent::setUp();

        $this->resetAfterTest();
        $this->course = $this->getDataGenerator()->create_course();
        $this->kanbanlearning = $this->getDataGenerator()->create_module('kanbanlearning', ['course' => $this->course]);

        for ($i = 0; $i < 3; $i++) {
            $this->users[$i] = $this->getDataGenerator()->create_user(
                [
                    'email' => $i . 'user@example.com',
                    'username' => 'userid' . $i,
                ]
            );
        }

        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        $teacherrole = $DB->get_record('role', ['shortname' => 'editingteacher']);
        $this->getDataGenerator()->enrol_user($this->users[0]->id, $this->course->id, $studentrole->id);
        $this->getDataGenerator()->enrol_user($this->users[1]->id, $this->course->id, $studentrole->id);
        $this->getDataGenerator()->enrol_user($this->users[2]->id, $this->course->id, $teacherrole->id);
    }

    /**
     * Test for creating a (course) board.
     *
     * @return void
     */
    public function test_create_board(): void {
        global $DB;

        $this->resetAfterTest();

        $boardmanager = new boardmanager($this->kanbanlearning->cmid);
        $boards = $DB->get_records('kanbanlearning_board', ['kanbanlearning_instance' => $this->kanbanlearning->id]);
        $this->assertCount(1, $boards);
        $boardid = $boardmanager->create_board();
        $this->assertNotEquals(false, $boardid);
        $boards = $DB->get_records('kanbanlearning_board', ['kanbanlearning_instance' => $this->kanbanlearning->id]);
        $this->assertCount(2, $boards);
        // Board should consist of three columns without any cards as there is no template yet.
        $columns = $DB->get_records('kanbanlearning_column', ['kanbanlearning_board' => $boardid]);
        $this->assertCount(3, $columns);
        $cards = $DB->get_records('kanbanlearning_card', ['kanbanlearning_board' => $boardid]);
        $this->assertCount(0, $cards);
    }

    /**
     * Test for deleting a board.
     *
     * @return void
     */
    public function test_delete_board(): void {
        global $DB;

        $this->resetAfterTest();

        $boardmanager = new boardmanager($this->kanbanlearning->cmid);
        $boardcount = $DB->count_records('kanbanlearning_board', ['kanbanlearning_instance' => $this->kanbanlearning->id]);
        $boardid = $boardmanager->create_board();
        $this->assertEquals(
            $boardcount + 1,
            $DB->count_records('kanbanlearning_board', ['kanbanlearning_instance' => $this->kanbanlearning->id])
        );
        $this->assertEquals(3, $DB->count_records('kanbanlearning_column', ['kanbanlearning_board' => $boardid]));

        $boardmanager->delete_board($boardid);
        $this->assertEquals(
            $boardcount,
            $DB->count_records('kanbanlearning_board', ['kanbanlearning_instance' => $this->kanbanlearning->id])
        );
        $this->assertEquals(0, $DB->count_records('kanbanlearning_column', ['kanbanlearning_board' => $boardid]));
    }

    /**
     * Test for creating a card.
     *
     * @return void
     */
    public function test_add_card(): void {
        global $DB;

        $this->resetAfterTest();

        $boardmanager = new boardmanager($this->kanbanlearning->cmid);
        $boardid = $boardmanager->create_board();
        $boardmanager->load_board($boardid);
        $columnid = $DB->get_field('kanbanlearning_column', 'id', ['kanbanlearning_board' => $boardid], IGNORE_MULTIPLE);
        $cardid = $boardmanager->add_card($columnid, 0, ['title' => 'Testcard']);
        $card = $boardmanager->get_card($cardid);
        $this->assertEquals('Testcard', $card->title);
        $this->assertEquals($boardid, $card->kanbanlearning_board);
        $this->assertEquals($columnid, $card->kanbanlearning_column);

        $card2id = $boardmanager->add_card($columnid, $cardid, ['title' => 'Testcard2']);
        $column = $boardmanager->get_column($columnid);
        $this->assertEquals(join(',', [$cardid, $card2id]), $column->sequence);
    }

    /**
     * Cards created in a completion column must start completed.
     *
     * @return void
     */
    public function test_add_card_to_completion_column(): void {
        global $DB;

        $this->resetAfterTest();
        $boardmanager = new boardmanager($this->kanbanlearning->cmid);
        $boardid = $boardmanager->create_board();
        $boardmanager->load_board($boardid);
        $columnids = $DB->get_fieldset_select('kanbanlearning_column', 'id', 'kanbanlearning_board = :id', ['id' => $boardid]);
        $completioncolumnid = end($columnids);
        $DB->set_field('kanbanlearning_column', 'options', json_encode(['autoclose' => true]), ['id' => $completioncolumnid]);

        $cardid = $boardmanager->add_card($completioncolumnid, 0, ['title' => 'Completed card']);
        $card = $boardmanager->get_card($cardid);

        $this->assertEquals(1, (int) $card->completed);
        $this->assertEquals($completioncolumnid, (int) $card->kanbanlearning_column);
    }

    /**
     * Approval seals are restricted to completed cards and are invalidated by content edits or reopening.
     * @return void
     */
    public function test_approval_seal_lifecycle(): void {
        global $DB;
        $DB->set_field('kanbanlearning', 'approval_seals', 1, ['id' => $this->kanbanlearning->id]);
        $boardmanager = new boardmanager($this->kanbanlearning->cmid);
        $boardid = $boardmanager->create_board();
        $boardmanager->load_board($boardid);
        $columnids = $DB->get_fieldset_select('kanbanlearning_column', 'id', 'kanbanlearning_board = :id', ['id' => $boardid]);
        $completioncolumnid = end($columnids);
        $DB->set_field('kanbanlearning_column', 'options', json_encode(['autoclose' => true]), ['id' => $completioncolumnid]);
        $cardid = $boardmanager->add_card($completioncolumnid, 0, ['title' => 'Finished work']);

        $boardmanager->set_approval_seal($cardid, 'approved');
        $this->assertSame('approved', $DB->get_field('kanbanlearning_card', 'approval_seal', ['id' => $cardid]));
        $boardmanager->update_card($cardid, ['repeat_interval' => 2]);
        $this->assertSame('approved', $DB->get_field('kanbanlearning_card', 'approval_seal', ['id' => $cardid]));
        $copyid = $boardmanager->duplicate_card($cardid);
        $this->assertSame('', $DB->get_field('kanbanlearning_card', 'approval_seal', ['id' => $copyid]));
        $boardmanager->update_card($cardid, ['description' => 'The work changed']);
        $this->assertSame('', $DB->get_field('kanbanlearning_card', 'approval_seal', ['id' => $cardid]));

        // Keep every supported reaction key covered so accidental renames are caught.
        foreach (['approved', 'clap', 'highlight', 'reflect'] as $seal) {
            $boardmanager->set_approval_seal($cardid, $seal);
            $this->assertSame($seal, $DB->get_field('kanbanlearning_card', 'approval_seal', ['id' => $cardid]));
        }

        // An empty value removes the reaction.
        $boardmanager->set_approval_seal($cardid, '');
        $this->assertSame('', $DB->get_field('kanbanlearning_card', 'approval_seal', ['id' => $cardid]));

        $boardmanager->set_approval_seal($cardid, 'highlight');
        $boardmanager->set_card_complete($cardid, 0);
        $this->assertSame('', $DB->get_field('kanbanlearning_card', 'approval_seal', ['id' => $cardid]));
    }

    /**
     * Unsupported reaction keys must not be persisted.
     * @return void
     */
    /**
     * Sending a card copy to another board does not carry or retain its reaction.
     *
     * @return void
     */
    public function test_push_card_copy_does_not_copy_approval_seal(): void {
        global $DB;

        $DB->set_field('kanbanlearning', 'approval_seals', 1, ['id' => $this->kanbanlearning->id]);
        $boardmanager = new boardmanager($this->kanbanlearning->cmid);
        $sourceboardid = $boardmanager->create_board();
        $targetboardid = $boardmanager->create_board();
        $otherboardid = $boardmanager->create_board();
        $boardmanager->load_board($sourceboardid);
        $completioncolumnid = $boardmanager->get_first_completion_column();
        $cardid = $boardmanager->add_card($completioncolumnid, 0, ['title' => 'Completed card']);
        $boardmanager->set_approval_seal($cardid, 'approved');

        $boardmanager->push_card_copy($cardid, [$targetboardid]);
        $copy = $DB->get_record('kanbanlearning_card', [
            'kanbanlearning_board' => $targetboardid,
            'originalid' => $cardid,
        ], '*', MUST_EXIST);

        $this->assertSame('', $copy->approval_seal);
        $this->assertFalse($DB->record_exists('kanbanlearning_card', [
            'kanbanlearning_board' => $otherboardid,
            'originalid' => $cardid,
        ]));

        $DB->set_field('kanbanlearning_card', 'approval_seal', 'highlight', ['id' => $copy->id]);
        $boardmanager->push_card_copy($cardid, [$targetboardid]);

        $this->assertSame('', $DB->get_field('kanbanlearning_card', 'approval_seal', ['id' => $copy->id]));
        $this->assertSame('approved', $DB->get_field('kanbanlearning_card', 'approval_seal', ['id' => $cardid]));
    }
    public function test_approval_seal_rejects_unknown_key(): void {
        global $DB;

        $DB->set_field('kanbanlearning', 'approval_seals', 1, ['id' => $this->kanbanlearning->id]);
        $boardmanager = new boardmanager($this->kanbanlearning->cmid);
        $boardid = $boardmanager->create_board();
        $boardmanager->load_board($boardid);
        $columnids = $DB->get_fieldset_select('kanbanlearning_column', 'id', 'kanbanlearning_board = :id', ['id' => $boardid]);
        $completioncolumnid = end($columnids);
        $DB->set_field('kanbanlearning_column', 'options', json_encode(['autoclose' => true]), ['id' => $completioncolumnid]);
        $cardid = $boardmanager->add_card($completioncolumnid, 0, ['title' => 'Finished work']);

        $this->expectException(\invalid_parameter_exception::class);
        $boardmanager->set_approval_seal($cardid, 'not-a-reaction');
    }

    /**
     * A reaction is unavailable when its activity setting is disabled.
     * @return void
     */
    public function test_approval_seal_requires_enabled_activity(): void {
        global $DB;

        $boardmanager = new boardmanager($this->kanbanlearning->cmid);
        $boardid = $boardmanager->create_board();
        $boardmanager->load_board($boardid);
        $columnids = $DB->get_fieldset_select('kanbanlearning_column', 'id', 'kanbanlearning_board = :id', ['id' => $boardid]);
        $completioncolumnid = end($columnids);
        $DB->set_field('kanbanlearning_column', 'options', json_encode(['autoclose' => true]), ['id' => $completioncolumnid]);
        $cardid = $boardmanager->add_card($completioncolumnid, 0, ['title' => 'Finished work']);

        $this->expectException(\moodle_exception::class);
        $boardmanager->set_approval_seal($cardid, 'approved');
    }

    /**
     * A reaction is unavailable for a card outside a completion column.
     * @return void
     */
    public function test_approval_seal_requires_completion_column(): void {
        global $DB;

        $DB->set_field('kanbanlearning', 'approval_seals', 1, ['id' => $this->kanbanlearning->id]);
        $boardmanager = new boardmanager($this->kanbanlearning->cmid);
        $boardid = $boardmanager->create_board();
        $boardmanager->load_board($boardid);
        $columns = $DB->get_records('kanbanlearning_column', ['kanbanlearning_board' => $boardid], 'id ASC');
        $normalcolumn = reset($columns);
        $normalcolumn->title = 'Work in progress';
        $normalcolumn->options = '{}';
        $DB->update_record('kanbanlearning_column', $normalcolumn);
        $cardid = $boardmanager->add_card($normalcolumn->id, 0, ['title' => 'Work in progress']);
        $DB->set_field('kanbanlearning_card', 'completed', 1, ['id' => $cardid]);

        $this->expectException(\moodle_exception::class);
        $boardmanager->set_approval_seal($cardid, 'approved');
    }

    /**
     * Card colors must be persisted in the card options.
     *
     * @return void
     */
    public function test_update_card_color(): void {
        global $DB;

        $this->resetAfterTest();
        $boardmanager = new boardmanager($this->kanbanlearning->cmid);
        $boardid = $boardmanager->create_board();
        $boardmanager->load_board($boardid);
        $columnid = $DB->get_field('kanbanlearning_column', 'id', ['kanbanlearning_board' => $boardid], IGNORE_MULTIPLE);
        $cardid = $boardmanager->add_card($columnid, 0, ['title' => 'Colored card']);

        $boardmanager->update_card($cardid, ['currentcolor' => '#F6EEB9']);
        $card = $DB->get_record('kanbanlearning_card', ['id' => $cardid], '*', MUST_EXIST);
        $options = json_decode($card->options, true);
        $this->assertEquals('#F6EEB9', $options['background']);
    }
    /**
     * Test for moving a card.
     *
     * @return void
     */
    public function test_move_card(): void {
        global $DB;

        $this->resetAfterTest();

        $boardmanager = new boardmanager($this->kanbanlearning->cmid);
        $boardid = $boardmanager->create_board();
        $boardmanager->load_board($boardid);
        $columnids = $DB->get_fieldset_select('kanbanlearning_column', 'id', 'kanbanlearning_board = :id', ['id' => $boardid]);
        // Add one card to each column (three columns expected).
        $cards = [];
        foreach ($columnids as $columnid) {
            $cardid = $boardmanager->add_card($columnid, 0, ['title' => 'Testcard']);
            $cards[] = $boardmanager->get_card($cardid);
        }
        $boardmanager->move_card($cards[0]->id, 0, $columnids[2]);
        $cards[0] = $boardmanager->get_card($cards[0]->id);
        $this->assertEquals($columnids[2], $cards[0]->kanbanlearning_column);

        $column = $boardmanager->get_column($columnids[0]);
        $this->assertEquals('', $column->sequence);

        $column = $boardmanager->get_column($columnids[2]);
        $this->assertEquals(join(',', [$cards[0]->id, $cards[2]->id]), $column->sequence);

        $boardmanager->move_card($cards[0]->id, $cards[2]->id);
        $cards[0] = $boardmanager->get_card($cards[0]->id);
        $this->assertEquals($columnids[2], $cards[0]->kanbanlearning_column);

        $column = $boardmanager->get_column($columnids[2]);
        $this->assertEquals($column->sequence, join(',', [$cards[2]->id, $cards[0]->id]));

        $boardmanager->move_card($cards[1]->id, $cards[2]->id, $columnids[2]);
        $cards[1] = $boardmanager->get_card($cards[1]->id);
        $this->assertEquals($columnids[2], $cards[1]->kanbanlearning_column);

        $column = $boardmanager->get_column($columnids[2]);
        $this->assertEquals($column->sequence, join(',', [$cards[2]->id, $cards[1]->id, $cards[0]->id]));
    }

    /**
     * Test for deleting a card.
     *
     * @return void
     */
    public function test_delete_card(): void {
        global $DB;

        $this->resetAfterTest();

        $boardmanager = new boardmanager($this->kanbanlearning->cmid);
        $boardid = $boardmanager->create_board();
        $boardmanager->load_board($boardid);
        $columnids = $DB->get_fieldset_select('kanbanlearning_column', 'id', 'kanbanlearning_board = :id', ['id' => $boardid]);

        $cardid = $boardmanager->add_card($columnids[0], 0, ['title' => 'Testcard']);
        $boardmanager->delete_card($cardid);
        $this->assertEquals(0, $DB->count_records('kanbanlearning_card', ['id' => $cardid]));

        $column = $boardmanager->get_column($columnids[0]);
        $this->assertEquals('', $column->sequence);

        // ToDo: Test deleting history / discussion here.
    }

    /**
     * Test for creating a column.
     *
     * @return void
     */
    public function test_add_column(): void {
        global $DB;

        $this->resetAfterTest();

        $boardmanager = new boardmanager($this->kanbanlearning->cmid);
        $boardid = $boardmanager->create_board();
        $boardmanager->load_board($boardid);
        $columnids = $DB->get_fieldset_select('kanbanlearning_column', 'id', 'kanbanlearning_board = :id', ['id' => $boardid]);
        $columnid = $boardmanager->add_column(0, ['title' => 'Testcolumn']);
        $columnids = array_merge([$columnid], $columnids);
        $boardmanager->load_board($boardid);
        $this->assertEquals(join(',', $columnids), $boardmanager->get_board()->sequence);

        $this->assertEquals(1, $DB->count_records('kanbanlearning_column', ['id' => $columnid]));

        $columnid = $boardmanager->add_column($columnids[3], ['title' => 'Testcolumn 2']);
        $columnids = array_merge($columnids, [$columnid]);
        $boardmanager->load_board($boardid);
        $this->assertEquals(join(',', $columnids), $boardmanager->get_board()->sequence);

        $this->assertEquals(1, $DB->count_records('kanbanlearning_column', ['id' => $columnid]));
    }

    /**
     * Test for creating a column.
     *
     * @return void
     */
    public function test_move_column(): void {
        global $DB;

        $this->resetAfterTest();

        $boardmanager = new boardmanager($this->kanbanlearning->cmid);
        $boardid = $boardmanager->create_board();
        $boardmanager->load_board($boardid);
        $columnids = $DB->get_fieldset_select('kanbanlearning_column', 'id', 'kanbanlearning_board = :id', ['id' => $boardid]);
        $boardmanager->move_column($columnids[2], 0);
        $boardmanager->load_board($boardid);
        $this->assertEquals(join(',', [$columnids[2], $columnids[0], $columnids[1]]), $boardmanager->get_board()->sequence);

        $boardmanager->move_column($columnids[0], $columnids[1]);
        $boardmanager->load_board($boardid);
        $this->assertEquals(join(',', [$columnids[2], $columnids[1], $columnids[0]]), $boardmanager->get_board()->sequence);
    }

    /**
     * Test for deleting a column.
     *
     * @return void
     */
    public function test_delete_column(): void {
        global $DB;

        $this->resetAfterTest();

        $boardmanager = new boardmanager($this->kanbanlearning->cmid);
        $boardid = $boardmanager->create_board();
        $boardmanager->load_board($boardid);
        $columncount = $DB->count_records('kanbanlearning_column', ['kanbanlearning_board' => $boardid]);
        $columnids = $DB->get_fieldset_select('kanbanlearning_column', 'id', 'kanbanlearning_board = :id', ['id' => $boardid]);

        $boardmanager->delete_column($columnids[0]);
        $this->assertEquals($columncount - 1, $DB->count_records('kanbanlearning_column', ['kanbanlearning_board' => $boardid]));
        array_shift($columnids);
        $this->assertEquals(join(',', $columnids), $boardmanager->get_board()->sequence);
    }

    /**
     * Test for permission checking.
     *
     * @return void
     */
    public function test_can_user_manage_specific_card(): void {
        global $DB;

        $this->resetAfterTest();

        $boardmanager = new boardmanager($this->kanbanlearning->cmid);
        $boardid = $boardmanager->create_board();
        $boardmanager->load_board($boardid);
        $columnids = $DB->get_fieldset_select('kanbanlearning_column', 'id', 'kanbanlearning_board = :id', ['id' => $boardid]);

        // Teacher user.
        $this->setUser($this->users[2]);
        $teachercard = $boardmanager->add_card($columnids[0]);
        $boardmanager->assign_user($teachercard, $this->users[1]->id);
        $teachercardstudentassigned = $boardmanager->add_card($columnids[0]);
        $boardmanager->assign_user($teachercardstudentassigned, $this->users[0]->id);

        // Student user.
        $this->setUser($this->users[0]);
        $studentcard = $boardmanager->add_card($columnids[1]);

        // Student user should not be able to edit a card created by the teacher.
        $this->assertEquals(false, $boardmanager->can_user_manage_specific_card($teachercard));
        // Student user should be able to edit a card he is assigned to.
        $this->assertEquals(true, $boardmanager->can_user_manage_specific_card($teachercardstudentassigned));
        // Student user should be able to edit a card created by himself.
        $this->assertEquals(true, $boardmanager->can_user_manage_specific_card($studentcard));
        // Teacher user should be able to edit every card.
        $this->assertEquals(true, $boardmanager->can_user_manage_specific_card($studentcard, $this->users[2]->id));

        // Test explicitly the mod_kanbanlearning/manageallcards capability.
        $context = context_course::instance($boardmanager->get_cminfo()->course);
        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        // Current student user should not be able to edit teacher card.
        $this->assertEquals(false, $boardmanager->can_user_manage_specific_card($teachercard));
        assign_capability('mod/kanbanlearning:manageallcards', CAP_ALLOW, $studentrole->id, $context);
        // Current student user now should be able to also edit teacher card.
        $this->assertEquals(true, $boardmanager->can_user_manage_specific_card($teachercard));
    }

    /**
     * Tests the json sanitization function.
     *
     * @dataProvider sanitize_json_string_provider
     * @param string $json the json string to sanitize
     * @param string $expected the expected sanitized json string
     * @return void
     */
    public function test_sanitize_json_string(string $json, string $expected): void {
        $output = helper::sanitize_json_string($json);
        $this->assertEquals($expected, $output);
    }

    /**
     * Data Provider for self::test_sanitize_json_string.
     *
     * @return array[] containing the keys 'json' and 'expected'
     */
    public static function sanitize_json_string_provider(): array {
        return [
            [
                'json' => '{"test": "<b>bad html</b><script>console.log(\"bla\")</script>"}',
                'expected' => '{"test":"<b>bad html<\/b>"}',
            ],
            [
                'json' => '{"test": "<b>good html</b>"}',
                'expected' => '{"test":"<b>good html<\/b>"}',
            ],
            [
                'json' => '{"test": "<b>good html</b>","anotherkey": [{"nestedkey":"<script>console.log(\"bla\");</script>"}]}',
                'expected' => '{"test":"<b>good html<\/b>","anotherkey":[{"nestedkey":""}]}',
            ],
            [
                'json' => '{"te<script>console.log(\"bla\");</script>st": "<b>good html</b>"}',
                'expected' => '{"test":"<b>good html<\/b>"}',
            ],
        ];
    }

    /**
     * Applying a template requires confirmation before removing cards and preserves board identity.
     *
     * @return void
     */
    public function test_apply_template_requires_confirmation_before_overwriting_cards(): void {
        global $DB;

        $board = $DB->get_record('kanbanlearning_board', [
            'kanbanlearning_instance' => $this->kanbanlearning->id,
            'userid' => 0,
            'groupid' => 0,
            'template' => 0,
        ], '*', MUST_EXIST);
        $boardmanager = new boardmanager($this->kanbanlearning->cmid, (int)$board->id);
        $columnids = array_map('intval', explode(',', $board->sequence));
        $sourcecolumnid = $columnids[0];

        $DB->set_field('kanbanlearning_column', 'title', 'Template source column', ['id' => $sourcecolumnid]);
        $templateid = $boardmanager->create_template();
        $DB->set_field('kanbanlearning_column', 'title', 'Target-only column title', ['id' => $sourcecolumnid]);
        $cardid = $boardmanager->add_card($sourcecolumnid, 0, ['title' => 'Keep until confirmed']);

        $exception = null;
        try {
            $boardmanager->apply_template_to_board((int)$board->id, $templateid, false);
        } catch (\moodle_exception $caught) {
            $exception = $caught;
        }

        $this->assertNotNull($exception);
        $this->assertSame('templateoverwriteconfirmationrequired', $exception->errorcode);
        $this->assertTrue($DB->record_exists('kanbanlearning_card', ['id' => $cardid]));
        $this->assertSame(
            'Target-only column title',
            $DB->get_field('kanbanlearning_column', 'title', ['id' => $sourcecolumnid])
        );

        $boardmanager->apply_template_to_board((int)$board->id, $templateid, true);

        $this->assertFalse($DB->record_exists('kanbanlearning_card', ['id' => $cardid]));
        $updatedboard = $DB->get_record('kanbanlearning_board', ['id' => $board->id], '*', MUST_EXIST);
        $this->assertSame((int)$board->id, (int)$updatedboard->id);
        $this->assertSame(0, (int)$updatedboard->userid);
        $this->assertSame(0, (int)$updatedboard->groupid);
        $this->assertSame(0, (int)$updatedboard->template);
        $updatedcolumnids = array_map('intval', explode(',', $updatedboard->sequence));
        $this->assertSame(
            'Template source column',
            $DB->get_field('kanbanlearning_column', 'title', ['id' => $updatedcolumnids[0]])
        );
    }

    /**
     * Bulk template application checks every group board before deleting any cards.
     *
     * @return void
     */
    public function test_apply_template_to_group_boards_checks_all_boards_before_overwriting(): void {
        global $DB;

        $this->setAdminUser();
        $groupa = $this->getDataGenerator()->create_group(['courseid' => $this->course->id, 'name' => 'Template Group A']);
        $groupb = $this->getDataGenerator()->create_group(['courseid' => $this->course->id, 'name' => 'Template Group B']);
        $DB->set_field(
            'kanbanlearning',
            'boardmode',
            constants::MOD_KANBANLEARNING_BOARDMODE_GROUP,
            ['id' => $this->kanbanlearning->id]
        );
        $DB->set_field(
            'kanbanlearning',
            'boardgroups',
            $groupa->id . ',' . $groupb->id,
            ['id' => $this->kanbanlearning->id]
        );

        $sharedboard = $DB->get_record('kanbanlearning_board', [
            'kanbanlearning_instance' => $this->kanbanlearning->id,
            'userid' => 0,
            'groupid' => 0,
            'template' => 0,
        ], '*', MUST_EXIST);
        $boardmanager = new boardmanager($this->kanbanlearning->cmid, (int)$sharedboard->id);
        $sharedcolumnids = array_map('intval', explode(',', $sharedboard->sequence));
        $DB->set_field('kanbanlearning_column', 'title', 'Bulk template source', ['id' => $sharedcolumnids[0]]);
        $templateid = $boardmanager->create_template();

        $groupboarda = $boardmanager->get_or_create_board(0, (int)$groupa->id);
        $groupboardb = $boardmanager->get_or_create_board(0, (int)$groupb->id);
        $groupcolumns = [];
        foreach ([$groupboarda, $groupboardb] as $boardid) {
            $groupboard = $DB->get_record('kanbanlearning_board', ['id' => $boardid], '*', MUST_EXIST);
            $columnids = array_map('intval', explode(',', $groupboard->sequence));
            $groupcolumns[$boardid] = $columnids[0];
            $DB->set_field('kanbanlearning_column', 'title', 'Group-specific title ' . $boardid, ['id' => $columnids[0]]);
        }
        $groupmanager = new boardmanager($this->kanbanlearning->cmid, (int)$groupboardb);
        $cardid = $groupmanager->add_card($groupcolumns[$groupboardb], 0, ['title' => 'Preserve until confirmed']);

        $exception = null;
        try {
            $boardmanager->apply_template_to_all_group_boards($templateid, false);
        } catch (\moodle_exception $caught) {
            $exception = $caught;
        }

        $this->assertNotNull($exception);
        $this->assertSame('templateoverwriteconfirmationrequired', $exception->errorcode);
        $this->assertTrue($DB->record_exists('kanbanlearning_card', ['id' => $cardid]));
        foreach ([$groupboarda, $groupboardb] as $boardid) {
            $this->assertSame(
                'Group-specific title ' . $boardid,
                $DB->get_field('kanbanlearning_column', 'title', ['id' => $groupcolumns[$boardid]])
            );
        }

        $boardmanager->apply_template_to_all_group_boards($templateid, true);

        $this->assertFalse($DB->record_exists('kanbanlearning_card', ['id' => $cardid]));
        foreach ([$groupboarda, $groupboardb] as $boardid) {
            $this->assertTrue($DB->record_exists('kanbanlearning_column', [
                'kanbanlearning_board' => $boardid,
                'title' => 'Bulk template source',
            ]));
        }
    }

}
