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

/**
 * Unit test for mod_kanbanlearning
 *
 * @package     mod_kanbanlearning
 * @copyright   2023-2024 ISB Bayern
 * @author      Stefan Hanauska <stefan.hanauska@csg-in.de>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \mod_kanbanlearning\external\change_kanbanlearning_content
 * @runTestsInSeparateProcesses
 */
final class change_kanbanlearning_content_test extends \advanced_testcase {
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
        global $DB, $SCRIPT;

        parent::setUp();

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
        // This is just for the tests of auth_saml2 not to fail.
        $SCRIPT = '/mod/kanbanlearning/view.php';
    }

    /**
     * Test for creating a column.
     *
     * @return void
     */
    public function test_add_column(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/lib/externallib.php');

        $this->resetAfterTest();
        $this->setUser($this->users[2]);

        $boardmanager = new boardmanager($this->kanbanlearning->cmid);
        $boardid = $boardmanager->create_board();
        $boardmanager->load_board($boardid);
        $columnids = $DB->get_fieldset_select('kanbanlearning_column', 'id', 'kanbanlearning_board = :id', ['id' => $boardid]);

        $returnvalue = \mod_kanbanlearning\external\change_kanbanlearning_content::add_column(
            $this->kanbanlearning->cmid,
            $boardid,
            ['aftercol' => 0, 'title' => 'Testcolumn']
        );
        $returnvalue = \external_api::clean_returnvalue(
            \mod_kanbanlearning\external\change_kanbanlearning_content::add_column_returns(),
            $returnvalue
        );

        $update = json_decode($returnvalue['update'], true);

        $this->assertCount(2, $update);
        $this->assertEquals('board', $update[0]['name']);
        $this->assertEquals('columns', $update[1]['name']);
        $columnid = $update[1]['fields']['id'];

        $columnids = array_merge([$columnid], $columnids);
        $this->assertEquals(join(',', $columnids), $update[0]['fields']['sequence']);

        $this->assertEquals(1, $DB->count_records('kanbanlearning_column', ['id' => $columnid]));

        $returnvalue = \mod_kanbanlearning\external\change_kanbanlearning_content::add_column(
            $this->kanbanlearning->cmid,
            $boardid,
            ['aftercol' => $columnids[3], 'title' => 'Testcolumn 2']
        );
        $returnvalue = \external_api::clean_returnvalue(
            \mod_kanbanlearning\external\change_kanbanlearning_content::add_column_returns(),
            $returnvalue
        );

        $update = json_decode($returnvalue['update'], true);
        $this->assertCount(2, $update);
        $columnid = $update[1]['fields']['id'];

        $columnids = array_merge($columnids, [$columnid]);
        $this->assertEquals(join(',', $columnids), $update[0]['fields']['sequence']);

        $this->assertEquals(1, $DB->count_records('kanbanlearning_column', ['id' => $columnid]));
    }

    /**
     * Test for creating a card.
     *
     * @return void
     */
    public function test_add_card(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/lib/externallib.php');

        $this->resetAfterTest();
        $this->setUser($this->users[2]);

        $boardmanager = new boardmanager($this->kanbanlearning->cmid);
        $boardid = $boardmanager->create_board();
        $boardmanager->load_board($boardid);
        $columnid = $DB->get_field('kanbanlearning_column', 'id', ['kanbanlearning_board' => $boardid], IGNORE_MULTIPLE);
        $returnvalue = \mod_kanbanlearning\external\change_kanbanlearning_content::add_card(
            $this->kanbanlearning->cmid,
            $boardid,
            ['aftercard' => 0, 'columnid' => $columnid, 'title' => 'Testcard']
        );
        $returnvalue = \external_api::clean_returnvalue(
            \mod_kanbanlearning\external\change_kanbanlearning_content::add_card_returns(),
            $returnvalue
        );

        $update = json_decode($returnvalue['update'], true);

        $this->assertCount(2, $update);
        $this->assertEquals('cards', $update[0]['name']);
        $this->assertEquals('columns', $update[1]['name']);
        $cardid = $update[0]['fields']['id'];

        $card = $boardmanager->get_card($cardid);
        $this->assertEquals('Testcard', $card->title);
        $this->assertEquals($boardid, $update[0]['fields']['kanbanlearning_board']);
        $this->assertEquals($columnid, $update[0]['fields']['kanbanlearning_column']);
        $this->assertEquals($cardid, $update[1]['fields']['sequence']);

        $returnvalue = \mod_kanbanlearning\external\change_kanbanlearning_content::add_card(
            $this->kanbanlearning->cmid,
            $boardid,
            ['aftercard' => $cardid, 'columnid' => $columnid, 'title' => 'Testcard 2']
        );
        $returnvalue = \external_api::clean_returnvalue(
            \mod_kanbanlearning\external\change_kanbanlearning_content::add_card_returns(),
            $returnvalue
        );

        $update = json_decode($returnvalue['update'], true);
        $card2id = $update[0]['fields']['id'];
        $this->assertCount(2, $update);
        $this->assertEquals('cards', $update[0]['name']);
        $this->assertEquals('columns', $update[1]['name']);
        $this->assertEquals(join(',', [$cardid, $card2id]), $update[1]['fields']['sequence']);
    }

    /**
     * Test for moving a column.
     *
     * @return void
     */
    public function test_move_column(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/lib/externallib.php');

        $this->resetAfterTest();
        $this->setUser($this->users[2]);

        $boardmanager = new boardmanager($this->kanbanlearning->cmid);
        $boardid = $boardmanager->create_board();
        $boardmanager->load_board($boardid);
        $columnids = $DB->get_fieldset_select('kanbanlearning_column', 'id', 'kanbanlearning_board = :id', ['id' => $boardid]);
        $returnvalue = \mod_kanbanlearning\external\change_kanbanlearning_content::move_column(
            $this->kanbanlearning->cmid,
            $boardid,
            ['aftercol' => 0, 'columnid' => $columnids[2]]
        );
        $returnvalue = \external_api::clean_returnvalue(
            \mod_kanbanlearning\external\change_kanbanlearning_content::move_column_returns(),
            $returnvalue
        );

        $update = json_decode($returnvalue['update'], true);

        $this->assertCount(1, $update);
        $this->assertEquals('board', $update[0]['name']);

        $this->assertEquals(join(',', [$columnids[2], $columnids[0], $columnids[1]]), $update[0]['fields']['sequence']);

        $returnvalue = \mod_kanbanlearning\external\change_kanbanlearning_content::move_column(
            $this->kanbanlearning->cmid,
            $boardid,
            ['aftercol' => $columnids[1], 'columnid' => $columnids[0]]
        );
        $returnvalue = \external_api::clean_returnvalue(
            \mod_kanbanlearning\external\change_kanbanlearning_content::move_column_returns(),
            $returnvalue
        );

        $update = json_decode($returnvalue['update'], true);

        $this->assertCount(1, $update);
        $this->assertEquals('board', $update[0]['name']);

        $this->assertEquals(join(',', [$columnids[2], $columnids[1], $columnids[0]]), $update[0]['fields']['sequence']);
    }

    /**
     * Test for moving a card.
     *
     * @return void
     */
    public function test_move_card(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/lib/externallib.php');

        $this->resetAfterTest();
        $this->setUser($this->users[2]);

        $boardmanager = new boardmanager($this->kanbanlearning->cmid);
        $boardid = $boardmanager->create_board();
        $boardmanager->load_board($boardid);
        $columnids = $DB->get_fieldset_select('kanbanlearning_column', 'id', 'kanbanlearning_board = :id', ['id' => $boardid]);
        $cards = [];
        foreach ($columnids as $columnid) {
            $cardid = $boardmanager->add_card($columnid, 0, ['title' => 'Testcard']);
            $cards[] = $boardmanager->get_card($cardid);
        }
        $returnvalue = \mod_kanbanlearning\external\change_kanbanlearning_content::move_card(
            $this->kanbanlearning->cmid,
            $boardid,
            ['cardid' => $cards[0]->id, 'aftercard' => 0, 'columnid' => $columnids[2]]
        );
        $returnvalue = \external_api::clean_returnvalue(
            \mod_kanbanlearning\external\change_kanbanlearning_content::move_card_returns(),
            $returnvalue
        );

        $update = json_decode($returnvalue['update'], true);

        // As the target column has autoclose enabled by default, we get two updates for cards.
        $this->assertCount(4, $update);
        $this->assertEquals('cards', $update[0]['name']);
        $this->assertEquals('columns', $update[1]['name']);
        $this->assertEquals('columns', $update[2]['name']);
        $this->assertEquals('cards', $update[3]['name']);

        $this->assertEquals(join(',', [$cards[0]->id, $cards[2]->id]), $update[2]['fields']['sequence']);
        $this->assertEquals('', $update[1]['fields']['sequence']);
        $this->assertEquals($columnids[2], $update[0]['fields']['kanbanlearning_column']);

        $returnvalue = \mod_kanbanlearning\external\change_kanbanlearning_content::move_card(
            $this->kanbanlearning->cmid,
            $boardid,
            ['cardid' => $cards[0]->id, 'aftercard' => $cards[2]->id, 'columnid' => $columnids[2]]
        );
        $returnvalue = \external_api::clean_returnvalue(
            \mod_kanbanlearning\external\change_kanbanlearning_content::move_card_returns(),
            $returnvalue
        );

        $update = json_decode($returnvalue['update'], true);

        $this->assertCount(1, $update);
        $this->assertEquals('columns', $update[0]['name']);

        $this->assertEquals(join(',', [$cards[2]->id, $cards[0]->id]), $update[0]['fields']['sequence']);

        $returnvalue = \mod_kanbanlearning\external\change_kanbanlearning_content::move_card(
            $this->kanbanlearning->cmid,
            $boardid,
            ['cardid' => $cards[1]->id, 'aftercard' => $cards[2]->id, 'columnid' => $columnids[2]]
        );
        $returnvalue = \external_api::clean_returnvalue(
            \mod_kanbanlearning\external\change_kanbanlearning_content::move_card_returns(),
            $returnvalue
        );

        $update = json_decode($returnvalue['update'], true);

        // As the target column has autoclose enabled by default, we get two updates for cards.
        $this->assertCount(4, $update);
        $this->assertEquals('cards', $update[0]['name']);
        $this->assertEquals('columns', $update[1]['name']);
        $this->assertEquals('columns', $update[2]['name']);
        $this->assertEquals('cards', $update[3]['name']);

        $this->assertEquals(join(',', [$cards[2]->id, $cards[1]->id, $cards[0]->id]), $update[2]['fields']['sequence']);
        $this->assertEquals('', $update[1]['fields']['sequence']);
        $this->assertEquals($columnids[2], $update[0]['fields']['kanbanlearning_column']);
    }

    /**
     * Test for deleting a card.
     *
     * @return void
     */
    public function test_delete_card(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/lib/externallib.php');

        $this->resetAfterTest();
        $this->setUser($this->users[2]);

        $boardmanager = new boardmanager($this->kanbanlearning->cmid);
        $boardid = $boardmanager->create_board();
        $boardmanager->load_board($boardid);
        $columnids = $DB->get_fieldset_select('kanbanlearning_column', 'id', 'kanbanlearning_board = :id', ['id' => $boardid]);
        $cards = [];
        foreach ($columnids as $columnid) {
            $cardid = $boardmanager->add_card($columnid, 0, ['title' => 'Testcard']);
            $cards[] = $boardmanager->get_card($cardid);
        }
        $returnvalue = \mod_kanbanlearning\external\change_kanbanlearning_content::delete_card(
            $this->kanbanlearning->cmid,
            $boardid,
            ['cardid' => $cards[0]->id]
        );
        $returnvalue = \external_api::clean_returnvalue(
            \mod_kanbanlearning\external\change_kanbanlearning_content::delete_card_returns(),
            $returnvalue
        );

        $update = json_decode($returnvalue['update'], true);

        $this->assertCount(2, $update);
        $this->assertEquals('columns', $update[0]['name']);
        $this->assertEquals('cards', $update[1]['name']);

        $this->assertEquals('', $update[0]['fields']['sequence']);
        $this->assertEquals($cards[0]->id, $update[1]['fields']['id']);

        // ToDo: Test deleting history / discussion here.
    }

    /**
     * Test for deleting a column.
     *
     * @return void
     */
    public function test_delete_column(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/lib/externallib.php');

        $this->resetAfterTest();
        $this->setUser($this->users[2]);

        $boardmanager = new boardmanager($this->kanbanlearning->cmid);
        $boardid = $boardmanager->create_board();
        $boardmanager->load_board($boardid);
        $columnids = $DB->get_fieldset_select('kanbanlearning_column', 'id', 'kanbanlearning_board = :id', ['id' => $boardid]);
        $cards = [];
        foreach ($columnids as $columnid) {
            $cardid = $boardmanager->add_card($columnid, 0, ['title' => 'Testcard']);
            $cards[] = $boardmanager->get_card($cardid);
        }
        $returnvalue = \mod_kanbanlearning\external\change_kanbanlearning_content::delete_column(
            $this->kanbanlearning->cmid,
            $boardid,
            ['columnid' => $columnids[0]]
        );
        $returnvalue = \external_api::clean_returnvalue(
            \mod_kanbanlearning\external\change_kanbanlearning_content::delete_column_returns(),
            $returnvalue
        );

        $update = json_decode($returnvalue['update'], true);

        $this->assertCount(3, $update);
        $this->assertEquals('cards', $update[0]['name']);
        $this->assertEquals('columns', $update[1]['name']);
        $this->assertEquals('board', $update[2]['name']);

        $this->assertEquals($cards[0]->id, $update[0]['fields']['id']);
        $this->assertEquals($columnids[0], $update[1]['fields']['id']);
        $this->assertEquals(join(',', [$columnids[1], $columnids[2]]), $update[2]['fields']['sequence']);
    }

    /**
     * Test for (un-)assigning an user to a card.
     *
     * @return void
     */
    public function test_assign_unassign_user(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/lib/externallib.php');

        $this->resetAfterTest();
        $this->setUser($this->users[2]);

        $boardmanager = new boardmanager($this->kanbanlearning->cmid);
        $boardid = $boardmanager->create_board();
        $boardmanager->load_board($boardid);
        $columnids = $DB->get_fieldset_select('kanbanlearning_column', 'id', 'kanbanlearning_board = :id', ['id' => $boardid]);
        $cards = [];
        foreach ($columnids as $columnid) {
            $cardid = $boardmanager->add_card($columnid, 0, ['title' => 'Testcard']);
            $cards[] = $boardmanager->get_card($cardid);
        }
        $returnvalue = \mod_kanbanlearning\external\change_kanbanlearning_content::assign_user(
            $this->kanbanlearning->cmid,
            $boardid,
            ['cardid' => $cards[2]->id, 'userid' => $this->users[0]->id]
        );
        $returnvalue = \external_api::clean_returnvalue(
            \mod_kanbanlearning\external\change_kanbanlearning_content::assign_user_returns(),
            $returnvalue
        );

        $update = json_decode($returnvalue['update'], true);

        $this->assertCount(2, $update);
        $updatesbyname = array_column($update, null, 'name');
        $this->assertArrayHasKey('cards', $updatesbyname);
        $this->assertArrayHasKey('users', $updatesbyname);
        $this->assertEquals([$this->users[0]->id], $updatesbyname['cards']['fields']['assignees']);

        $returnvalue = \mod_kanbanlearning\external\change_kanbanlearning_content::assign_user(
            $this->kanbanlearning->cmid,
            $boardid,
            ['cardid' => $cards[2]->id, 'userid' => $this->users[2]->id]
        );
        $returnvalue = \external_api::clean_returnvalue(
            \mod_kanbanlearning\external\change_kanbanlearning_content::assign_user_returns(),
            $returnvalue
        );

        $update = json_decode($returnvalue['update'], true);

        $updatesbyname = array_column($update, null, 'name');
        $this->assertArrayHasKey('cards', $updatesbyname);
        $this->assertArrayHasKey('users', $updatesbyname);
        $this->assertEquals([$this->users[0]->id, $this->users[2]->id], $updatesbyname['cards']['fields']['assignees']);

        $returnvalue = \mod_kanbanlearning\external\change_kanbanlearning_content::unassign_user(
            $this->kanbanlearning->cmid,
            $boardid,
            ['cardid' => $cards[2]->id, 'userid' => $this->users[0]->id]
        );
        $returnvalue = \external_api::clean_returnvalue(
            \mod_kanbanlearning\external\change_kanbanlearning_content::unassign_user_returns(),
            $returnvalue
        );

        $update = json_decode($returnvalue['update'], true);

        $this->assertCount(1, $update);
        $this->assertEquals('cards', $update[0]['name']);
        $this->assertEquals([$this->users[2]->id], $update[0]['fields']['assignees']);

        $returnvalue = \mod_kanbanlearning\external\change_kanbanlearning_content::unassign_user(
            $this->kanbanlearning->cmid,
            $boardid,
            ['cardid' => $cards[2]->id, 'userid' => $this->users[2]->id]
        );
        $returnvalue = \external_api::clean_returnvalue(
            \mod_kanbanlearning\external\change_kanbanlearning_content::unassign_user_returns(),
            $returnvalue
        );

        $update = json_decode($returnvalue['update'], true);

        $this->assertCount(1, $update);
        $this->assertEquals('cards', $update[0]['name']);
        $this->assertEquals([], $update[0]['fields']['assignees']);
    }

    /**
     * Test for setting completion status of a card.
     *
     * @return void
     */
    public function test_set_card_complete(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/lib/externallib.php');

        $this->resetAfterTest();
        $this->setUser($this->users[2]);

        $boardmanager = new boardmanager($this->kanbanlearning->cmid);
        $boardid = $boardmanager->create_board();
        $boardmanager->load_board($boardid);
        $completedcolumnid = $boardmanager->get_first_completion_column($boardid);
        $this->assertNotEmpty($completedcolumnid);
        $cardid = $boardmanager->add_card($completedcolumnid, 0, ['title' => 'Testcard']);
        $returnvalue = \mod_kanbanlearning\external\change_kanbanlearning_content::set_card_complete(
            $this->kanbanlearning->cmid,
            $boardid,
            ['cardid' => $cardid, 'state' => 1]
        );
        $returnvalue = \external_api::clean_returnvalue(
            \mod_kanbanlearning\external\change_kanbanlearning_content::set_card_complete_returns(),
            $returnvalue
        );

        $update = json_decode($returnvalue['update'], true);

        $this->assertCount(1, $update);
        $this->assertEquals('cards', $update[0]['name']);
        $this->assertEquals(1, $update[0]['fields']['completed']);

        $completedcard = $DB->get_record('kanbanlearning_card', ['id' => $cardid], '*', MUST_EXIST);
        $this->assertEquals((int) $completedcolumnid, (int) $completedcard->kanbanlearning_column);

        $returnvalue = \mod_kanbanlearning\external\change_kanbanlearning_content::set_card_complete(
            $this->kanbanlearning->cmid,
            $boardid,
            ['cardid' => $cardid, 'state' => 0]
        );
        $returnvalue = \external_api::clean_returnvalue(
            \mod_kanbanlearning\external\change_kanbanlearning_content::set_card_complete_returns(),
            $returnvalue
        );

        $update = json_decode($returnvalue['update'], true);

        $this->assertCount(1, $update);
        $this->assertEquals('cards', $update[0]['name']);
        $this->assertEquals(0, $update[0]['fields']['completed']);
    }

    /**
     * Setting a reaction persists it and returns the updated card fields.
     * @return void
     */
    public function test_set_approval_seal(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/lib/externallib.php');

        $this->resetAfterTest();
        $this->setUser($this->users[2]);
        $DB->set_field('kanbanlearning', 'approval_seals', 1, ['id' => $this->kanbanlearning->id]);

        $boardmanager = new boardmanager($this->kanbanlearning->cmid);
        $boardid = $boardmanager->create_board();
        $boardmanager->load_board($boardid);
        $completioncolumnid = $boardmanager->get_first_completion_column($boardid);
        $this->assertNotEmpty($completioncolumnid);
        $cardid = $boardmanager->add_card($completioncolumnid, 0, ['title' => 'Finished work']);

        $returnvalue = \mod_kanbanlearning\external\change_kanbanlearning_content::set_approval_seal(
            $this->kanbanlearning->cmid,
            $boardid,
            ['cardid' => $cardid, 'seal' => 'clap']
        );
        $returnvalue = \external_api::clean_returnvalue(
            \mod_kanbanlearning\external\change_kanbanlearning_content::set_approval_seal_returns(),
            $returnvalue
        );
        $update = json_decode($returnvalue['update'], true);

        $this->assertSame('clap', $DB->get_field('kanbanlearning_card', 'approval_seal', ['id' => $cardid]));
        $this->assertCount(1, $update);
        $this->assertSame('cards', $update[0]['name']);
        $this->assertSame('clap', $update[0]['fields']['approval_seal']);
        $this->assertSame('👏', $update[0]['fields']['approval_seal_icon']);
    }

    /**
     * Students cannot set teacher reactions.
     * @return void
     */
    public function test_set_approval_seal_requires_capability(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/lib/externallib.php');

        $this->resetAfterTest();
        $DB->set_field('kanbanlearning', 'approval_seals', 1, ['id' => $this->kanbanlearning->id]);
        $boardmanager = new boardmanager($this->kanbanlearning->cmid);
        $boardid = $boardmanager->create_board();
        $boardmanager->load_board($boardid);
        $completioncolumnid = $boardmanager->get_first_completion_column($boardid);
        $cardid = $boardmanager->add_card($completioncolumnid, 0, ['title' => 'Finished work']);
        $this->setUser($this->users[0]);

        $this->expectException(\required_capability_exception::class);
        \mod_kanbanlearning\external\change_kanbanlearning_content::set_approval_seal(
            $this->kanbanlearning->cmid,
            $boardid,
            ['cardid' => $cardid, 'seal' => 'approved']
        );
    }
}
