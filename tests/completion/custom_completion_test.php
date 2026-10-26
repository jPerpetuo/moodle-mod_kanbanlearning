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

namespace mod_kanbanlearning\completion;

/**
 * Tests for Kanban Learning custom completion rules.
 *
 * @package     mod_kanbanlearning
 * @copyright   2026 CCEAD PUC-Rio
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \mod_kanbanlearning\completion\custom_completion
 */
final class custom_completion_test extends \advanced_testcase {
    /**
     * Custom completion rules are exposed in their configured order.
     *
     * @return void
     */
    public function test_get_defined_custom_rules(): void {
        $this->assertSame(
            ['completioncreate', 'completioncomplete'],
            custom_completion::get_defined_custom_rules()
        );
    }

    /**
     * The create rule counts only cards created by the target user and honors its threshold.
     *
     * @return void
     */
    public function test_create_rule_counts_target_user_cards_up_to_threshold(): void {
        $fixture = $this->create_fixture(2, 0);
        $manager = $fixture['manager'];
        $this->add_card($manager, $fixture['columnid'], $fixture['student']);
        $completion = new custom_completion($fixture['cm'], $fixture['student']->id);
        $this->assertSame(COMPLETION_INCOMPLETE, $completion->get_state('completioncreate'));

        $this->add_card($manager, $fixture['columnid'], $fixture['otheruser']);
        $this->assertSame(COMPLETION_INCOMPLETE, $completion->get_state('completioncreate'));

        $this->add_card($manager, $fixture['columnid'], $fixture['student']);
        $this->assertSame(COMPLETION_COMPLETE, $completion->get_state('completioncreate'));
    }

    /**
     * The complete rule counts only completed cards assigned to the target user.
     *
     * @return void
     */
    public function test_complete_rule_counts_assigned_completed_cards_up_to_threshold(): void {
        $fixture = $this->create_fixture(0, 2);
        $manager = $fixture['manager'];
        $studentcard = $this->add_card($manager, $fixture['columnid'], $fixture['otheruser'], true);
        $this->assign_card($studentcard, $fixture['student']->id);

        $incompletecard = $this->add_card($manager, $fixture['columnid'], $fixture['otheruser']);
        $this->assign_card($incompletecard, $fixture['student']->id);

        $otherusercard = $this->add_card($manager, $fixture['columnid'], $fixture['otheruser'], true);
        $this->assign_card($otherusercard, $fixture['otheruser']->id);

        $completion = new custom_completion($fixture['cm'], $fixture['student']->id);
        $this->assertSame(COMPLETION_INCOMPLETE, $completion->get_state('completioncomplete'));

        $secondcompletedcard = $this->add_card($manager, $fixture['columnid'], $fixture['otheruser'], true);
        $this->assign_card($secondcompletedcard, $fixture['student']->id);
        $this->assertSame(COMPLETION_COMPLETE, $completion->get_state('completioncomplete'));
    }

    /**
     * Create a course activity, board, users and a completion instance for the requested rules.
     *
     * @param int $completioncreate Required number of cards to create.
     * @param int $completioncomplete Required number of completed assigned cards.
     * @return array
     */
    private function create_fixture(int $completioncreate, int $completioncomplete): array {
        global $DB;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $activity = $this->getDataGenerator()->create_module('kanbanlearning', ['course' => $course]);
        $DB->set_field('kanbanlearning', 'completioncreate', $completioncreate, ['id' => $activity->id]);
        $DB->set_field('kanbanlearning', 'completioncomplete', $completioncomplete, ['id' => $activity->id]);

        $student = $this->getDataGenerator()->create_user();
        $otheruser = $this->getDataGenerator()->create_user();
        $manager = new \mod_kanbanlearning\boardmanager($activity->cmid);
        $boardid = $manager->create_board();
        $manager->load_board($boardid);
        $columnid = $DB->get_field(
            'kanbanlearning_column',
            'id',
            ['kanbanlearning_board' => $boardid],
            IGNORE_MULTIPLE
        );
        $cm = get_fast_modinfo($course->id, 0, true)->get_cm($activity->cmid);

        return [
            'cm' => $cm,
            'columnid' => (int) $columnid,
            'manager' => $manager,
            'otheruser' => $otheruser,
            'student' => $student,
        ];
    }

    /**
     * Add a card created by the supplied user and optionally mark it completed.
     *
     * @param \mod_kanbanlearning\boardmanager $manager Board manager.
     * @param int $columnid Target column.
     * @param \stdClass $creator Card creator.
     * @param bool $completed Whether the card is completed.
     * @return int Card ID.
     */
    private function add_card(\mod_kanbanlearning\boardmanager $manager, int $columnid, \stdClass $creator, bool $completed = false): int {
        global $DB;

        $this->setUser($creator);
        $cardid = $manager->add_card($columnid, 0, ['title' => 'Completion test card']);
        if ($completed) {
            $DB->set_field('kanbanlearning_card', 'completed', 1, ['id' => $cardid]);
        }
        return $cardid;
    }

    /**
     * Assign a card to the specified user.
     *
     * @param int $cardid Card ID.
     * @param int $userid User ID.
     * @return void
     */
    private function assign_card(int $cardid, int $userid): void {
        global $DB;

        $DB->insert_record('kanbanlearning_assignee', (object) [
            'kanbanlearning_card' => $cardid,
            'userid' => $userid,
        ]);
    }
}
