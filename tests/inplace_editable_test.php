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
 * Tests for the inplace editable callback.
 *
 * @package     mod_kanbanlearning
 * @copyright   2026 CCEAD PUC-Rio
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      ::kanbanlearning_inplace_editable
 * @runTestsInSeparateProcesses
 */
final class inplace_editable_test extends \\advanced_testcase {
    /**
     * Load the activity library for callback tests.
     *
     * @return void
     */
    public function setUp(): void {
        global $CFG;

        parent::setUp();
        require_once($CFG->dirroot . '/mod/kanbanlearning/lib.php');
    }

    /**
     * Teachers can rename cards and columns through the inplace editable callback.
     *
     * @return void
     */
    public function test_updates_card_and_column_titles_for_teacher(): void {
        global $DB;

        $fixture = $this->create_fixture();
        $this->setUser($fixture['teacher']);

        $cardeditable = \\kanbanlearning_inplace_editable('card', $fixture['cardid'], 'Updated card title');
        $columneditable = \\kanbanlearning_inplace_editable('column', $fixture['columnid'], 'Updated column title');

        $this->assertInstanceOf(\\core\\output\\inplace_editable::class, $cardeditable);
        $this->assertInstanceOf(\\core\\output\\inplace_editable::class, $columneditable);
        $this->assertSame('Updated card title', $DB->get_field('kanbanlearning_card', 'title', ['id' => $fixture['cardid']]));
        $this->assertSame('Updated column title', $DB->get_field('kanbanlearning_column', 'title', ['id' => $fixture['columnid']]));
    }

    /**
     * Students cannot rename cards assigned to another user.
     *
     * @return void
     */
    public function test_rejects_student_editing_card_assigned_to_another_user(): void {
        $fixture = $this->create_fixture();
        $this->setUser($fixture['student']);

        $this->expectException(\\moodle_exception::class);
        \\kanbanlearning_inplace_editable('card', $fixture['cardid'], 'Unauthorized title');
    }

    /**
     * Students cannot rename columns without the manage columns capability.
     *
     * @return void
     */
    public function test_rejects_student_editing_column(): void {
        $fixture = $this->create_fixture();
        $this->setUser($fixture['student']);

        $this->expectException(\\required_capability_exception::class);
        \\kanbanlearning_inplace_editable('column', $fixture['columnid'], 'Unauthorized column title');
    }

    /**
     * Create an activity, board, card and enrolled teacher and student.
     *
     * The card is assigned to the teacher so the student role's default
     * manageassignedcards permission cannot authorize editing it.
     *
     * @return array
     */
    private function create_fixture(): array {
        global $DB;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $activity = $this->getDataGenerator()->create_module('kanbanlearning', ['course' => $course]);
        $teacher = $this->getDataGenerator()->create_user();
        $student = $this->getDataGenerator()->create_user();
        $teacherrole = $DB->get_record('role', ['shortname' => 'editingteacher'], '*', MUST_EXIST);
        $studentrole = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, $teacherrole->id);
        $this->getDataGenerator()->enrol_user($student->id, $course->id, $studentrole->id);

        $boardmanager = new boardmanager($activity->cmid);
        $boardid = $boardmanager->create_board();
        $boardmanager->load_board($boardid);
        $columnid = $DB->get_field('kanbanlearning_column', 'id', ['kanbanlearning_board' => $boardid], IGNORE_MULTIPLE);
        $this->setUser($teacher);
        $cardid = $boardmanager->add_card($columnid, 0, ['title' => 'Original card title']);
        $DB->insert_record('kanbanlearning_assignee', (object) [
            'kanbanlearning_card' => $cardid,
            'userid' => $teacher->id,
        ]);

        return [
            'cardid' => $cardid,
            'columnid' => $columnid,
            'student' => $student,
            'teacher' => $teacher,
        ];
    }
}
