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

namespace mod_kanbanlearning\form;

use mod_kanbanlearning\boardmanager;

/**
 * Tests access checks for the dynamic column edit form.
 *
 * @package     mod_kanbanlearning
 * @copyright   2026 CCEAD PUC-Rio
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \mod_kanbanlearning\form\edit_column_form
 */
final class edit_column_form_test extends \advanced_testcase {
    /** @var \stdClass Course used by the tests. */
    private $course;

    /** @var \stdClass Activity used by the tests. */
    private $activity;

    /** @var \stdClass Teacher who can edit columns. */
    private $teacher;

    /** @var \stdClass Student who must not edit columns. */
    private $student;

    /** @var int Board id used by the tests. */
    private $boardid;

    /** @var int Column id used by the tests. */
    private $columnid;

    /** @var int Course module id used by the tests. */
    private $cmid;

    /**
     * Set up an activity, users, board and column.
     *
     * @return void
     */
    protected function setUp(): void {
        global $DB;

        parent::setUp();
        $this->resetAfterTest();

        $this->course = $this->getDataGenerator()->create_course();
        $this->activity = $this->getDataGenerator()->create_module('kanbanlearning', [
            'course' => $this->course,
        ]);
        $this->cmid = (int) $this->activity->cmid;

        $this->teacher = $this->getDataGenerator()->create_user();
        $this->student = $this->getDataGenerator()->create_user();
        $teacherrole = $DB->get_record('role', ['shortname' => 'editingteacher'], '*', MUST_EXIST);
        $studentrole = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);
        $this->getDataGenerator()->enrol_user($this->teacher->id, $this->course->id, $teacherrole->id);
        $this->getDataGenerator()->enrol_user($this->student->id, $this->course->id, $studentrole->id);

        $this->setUser($this->teacher);
        $boardmanager = new boardmanager($this->cmid);
        $this->boardid = (int) $boardmanager->get_or_create_board();
        $boardmanager->load_board($this->boardid);
        $columnids = $DB->get_fieldset_select(
            'kanbanlearning_column',
            'id',
            'kanbanlearning_board = :boardid',
            ['boardid' => $this->boardid]
        );
        $this->columnid = (int) reset($columnids);
    }

    /**
     * A teacher with the manage-columns capability can open the dynamic form.
     *
     * @return void
     */
    public function test_teacher_can_open_dynamic_edit_form(): void {
        $form = $this->create_dynamic_form();

        $this->assertInstanceOf(edit_column_form::class, $form);
    }

    /**
     * A student without the manage-columns capability cannot open the dynamic form.
     *
     * @return void
     */
    public function test_student_cannot_open_dynamic_edit_form(): void {
        $this->setUser($this->student);

        $this->expectException(\required_capability_exception::class);
        $this->create_dynamic_form();
    }

    /**
     * Create the form as an AJAX submission so Moodle runs its access checks.
     *
     * @return edit_column_form
     */
    private function create_dynamic_form(): edit_column_form {
        return new edit_column_form(
            null,
            null,
            'post',
            '',
            [],
            true,
            [
                'cmid' => $this->cmid,
                'boardid' => $this->boardid,
                'id' => $this->columnid,
            ],
            true
        );
    }
}