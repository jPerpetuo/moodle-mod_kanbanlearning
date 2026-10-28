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

use mod_kanbanlearning\external\get_kanbanlearning_content;

/**
 * Tests for the Kanban content external service.
 *
 * @package     mod_kanbanlearning
 * @copyright   2026 CCEAD PUC-Rio
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \mod_kanbanlearning\external\get_kanbanlearning_content
 * @runTestsInSeparateProcesses
 */
final class get_kanbanlearning_content_test extends \advanced_testcase {
    /** @var \stdClass Course used for testing. */
    private $course;

    /** @var \stdClass Kanban activity used for testing. */
    private $kanbanlearning;

    /** @var \stdClass Teacher used for testing. */
    private $teacher;

    /** @var \stdClass Student used for testing. */
    private $student;

    /**
     * Prepare a course, activity and users for the tests.
     *
     * @return void
     */
    public function setUp(): void {
        global $CFG, $DB;

        parent::setUp();
        $this->resetAfterTest();
        require_once($CFG->libdir . '/externallib.php');

        $this->course = $this->getDataGenerator()->create_course([
            'shortname' => 'kanbanlearning_test_' . bin2hex(random_bytes(8)),
        ]);
        $this->kanbanlearning = $this->getDataGenerator()->create_module(
            'kanbanlearning',
            ['course' => $this->course]
        );
        $DB->set_field('kanbanlearning', 'approval_seals', 1, ['id' => $this->kanbanlearning->id]);

        $this->teacher = $this->getDataGenerator()->create_user();
        $this->student = $this->getDataGenerator()->create_user();
        $teacherrole = $DB->get_record('role', ['shortname' => 'editingteacher'], '*', MUST_EXIST);
        $studentrole = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);
        $this->getDataGenerator()->enrol_user($this->teacher->id, $this->course->id, $teacherrole->id);
        $this->getDataGenerator()->enrol_user($this->student->id, $this->course->id, $studentrole->id);
        $this->setUser($this->teacher);
    }

    /**
     * Initial data marks a completed card as eligible for a teacher reaction.
     *
     * @return void
     */
    public function test_initial_data_marks_completed_card_as_reaction_eligible(): void {
        [$boardmanager, $boardid, $completioncolumnid, $normalcolumnid] = $this->create_board();
        $cardid = $boardmanager->add_card($completioncolumnid, 0, ['title' => 'Finished work']);

        $response = get_kanbanlearning_content::get_kanbanlearning_content_init(
            $this->kanbanlearning->cmid,
            $boardid
        );
        $card = $this->find_initial_card($response['cards'], $cardid);

        $this->assertSame(1, (int) $response['common']->approval_seals);
        $this->assertSame($completioncolumnid, (int) $response['common']->approvalcompletioncolumn);
        $this->assertSame(1, (int) $card->completed);
        $this->assertTrue($card->approval_seal_enabled);
        $this->assertTrue($card->can_manage_approval_seal);
        $this->assertSame('', $card->approval_seal_icon);
        $this->assertNotSame($completioncolumnid, $normalcolumnid);
    }

    /**
     * Incremental data enables reaction controls when a card enters the completion column.
     *
     * @return void
     */
    public function test_incremental_update_enables_reaction_when_card_enters_completion_column(): void {
        [$boardmanager, $boardid, $completioncolumnid, $normalcolumnid] = $this->create_board();
        $cardid = $boardmanager->add_card($normalcolumnid, 0, ['title' => 'Work in progress']);

        $boardmanager->move_card($cardid, 0, $completioncolumnid);
        $updates = $this->get_incremental_updates($boardid);
        $card = $this->find_updated_card($updates, $cardid);

        $this->assertSame(1, (int) $card['completed']);
        $this->assertSame(1, (int) $card['approval_seal_enabled']);
        $this->assertSame(1, (int) $card['can_manage_approval_seal']);
        $this->assertSame('', $card['approval_seal_icon']);
    }

    /**
     * Incremental data removes reaction controls and the seal when a card leaves completion.
     *
     * @return void
     */
    public function test_incremental_update_disables_reaction_when_card_leaves_completion_column(): void {
        [$boardmanager, $boardid, $completioncolumnid, $normalcolumnid] = $this->create_board();
        $cardid = $boardmanager->add_card($completioncolumnid, 0, ['title' => 'Finished work']);
        $boardmanager->set_approval_seal($cardid, 'approved');

        $boardmanager->move_card($cardid, 0, $normalcolumnid);
        $updates = $this->get_incremental_updates($boardid);
        $card = $this->find_updated_card($updates, $cardid);

        $this->assertSame(0, (int) $card['completed']);
        $this->assertFalse($card['approval_seal_enabled']);
        $this->assertSame('', $card['approval_seal']);
        $this->assertSame('', $card['approval_seal_icon']);
    }

    /**
     * Students receive the reaction for display but cannot manage teacher reactions.
     *
     * @return void
     */
    public function test_student_can_see_reaction_but_cannot_manage_it(): void {
        [$boardmanager, $boardid, $completioncolumnid] = $this->create_board();
        $cardid = $boardmanager->add_card($completioncolumnid, 0, ['title' => 'Finished work']);
        $boardmanager->set_approval_seal($cardid, 'approved');
        $this->setUser($this->student);

        $updates = $this->get_incremental_updates($boardid);
        $card = $this->find_updated_card($updates, $cardid);

        $this->assertSame(1, (int) $card['approval_seal_enabled']);
        $this->assertFalse($card['can_manage_approval_seal']);
        $this->assertSame('approved', $card['approval_seal']);
        $this->assertSame('✅', $card['approval_seal_icon']);
    }

    /**
     * Group members can read their board, while students in another group cannot.
     *
     * @return void
     */
    public function test_group_board_content_is_restricted_to_group_members(): void {
        global $DB;

        $groupa = $this->getDataGenerator()->create_group(['courseid' => $this->course->id]);
        $groupb = $this->getDataGenerator()->create_group(['courseid' => $this->course->id]);
        groups_add_member($groupa->id, $this->student->id);

        $outsider = $this->getDataGenerator()->create_user();
        $studentrole = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);
        $this->getDataGenerator()->enrol_user($outsider->id, $this->course->id, $studentrole->id);
        groups_add_member($groupb->id, $outsider->id);

        $DB->set_field(
            'kanbanlearning',
            'boardmode',
            constants::MOD_KANBANLEARNING_BOARDMODE_GROUP,
            ['id' => $this->kanbanlearning->id]
        );
        $creator = new boardmanager($this->kanbanlearning->cmid);
        $boardid = $creator->create_group_board($groupa->id);
        $boardmanager = new boardmanager($this->kanbanlearning->cmid, $boardid);
        $columnids = $DB->get_fieldset_select(
            'kanbanlearning_column',
            'id',
            'kanbanlearning_board = :boardid',
            ['boardid' => $boardid]
        );
        $columnid = (int) reset($columnids);
        $cardid = $boardmanager->add_card($columnid, 0, ['title' => 'Group A private work']);

        $this->setUser($this->student);
        $response = get_kanbanlearning_content::get_kanbanlearning_content_init(
            $this->kanbanlearning->cmid,
            $boardid
        );
        $card = $this->find_initial_card($response['cards'], $cardid);
        $this->assertSame('Group A private work', $card->title);

        $this->setUser($outsider);
        try {
            get_kanbanlearning_content::get_kanbanlearning_content_init(
                $this->kanbanlearning->cmid,
                $boardid
            );
            $this->fail('A student outside the group must not receive its board content.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('boardgroupaccessdenied', $exception->errorcode);
        }
    }

    /**
     * Create a board with a deterministic completion column.
     *
     * @return array
     */
    private function create_board(): array {
        global $DB;

        $boardmanager = new boardmanager($this->kanbanlearning->cmid);
        $boardid = $boardmanager->get_or_create_board();
        $columnids = $DB->get_fieldset_sql(
            'SELECT id FROM {kanbanlearning_column} WHERE kanbanlearning_board = :boardid ORDER BY id ASC',
            ['boardid' => $boardid]
        );
        $completioncolumnid = (int) end($columnids);
        $normalcolumnid = (int) reset($columnids);
        $DB->set_field(
            'kanbanlearning_column',
            'options',
            json_encode(['autoclose' => true]),
            ['id' => $completioncolumnid]
        );
        $boardmanager->load_board($boardid);
        $completioncolumnid = $boardmanager->get_first_completion_column();

        $this->assertNotEmpty($completioncolumnid);
        $this->assertNotSame($completioncolumnid, $normalcolumnid);

        return [$boardmanager, $boardid, $completioncolumnid, $normalcolumnid];
    }

    /**
     * Get the incremental update payload for a board.
     *
     * @param int $boardid Board id.
     * @return array
     */
    private function get_incremental_updates(int $boardid): array {
        $response = get_kanbanlearning_content::get_kanbanlearning_content_update(
            $this->kanbanlearning->cmid,
            $boardid,
            0
        );
        return json_decode($response['update'], true);
    }

    /**
     * Find a card in the initial content response.
     *
     * @param array $cards Cards returned by the initial request.
     * @param int $cardid Card id.
     * @return stdClass
     */
    private function find_initial_card(array $cards, int $cardid): \stdClass {
        foreach ($cards as $card) {
            if ((int) $card->id === $cardid) {
                return $card;
            }
        }

        $this->fail('The initial response did not include the expected card.');
    }

    /**
     * Find a card update in the incremental response.
     *
     * @param array $updates Formatted updates.
     * @param int $cardid Card id.
     * @return array
     */
    private function find_updated_card(array $updates, int $cardid): array {
        foreach ($updates as $update) {
            if ($update['name'] === 'cards' && (int) $update['fields']['id'] === $cardid) {
                return $update['fields'];
            }
        }

        $this->fail('The incremental response did not include the expected card.');
    }
}
