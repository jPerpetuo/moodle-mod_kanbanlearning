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
 * Tests for Kanban activity library callbacks.
 *
 * @package     mod_kanbanlearning
 * @copyright   2026 CCEAD PUC-Rio
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      ::kanbanlearning_normalize_board_group_settings
 * @covers      ::kanbanlearning_get_coursemodule_info
 * @covers      ::kanbanlearning_supports
 * @covers      ::kanbanlearning_inplace_editable
 */
final class lib_callbacks_test extends \advanced_testcase {
    /** @var array Original POST data to restore after the test. */
    private $originalpost;

    /**
     * Test setup.
     *
     * @return void
     */
    public function setUp(): void {
        global $CFG;

        parent::setUp();
        $this->originalpost = $_POST;
        require_once($CFG->dirroot . '/mod/kanbanlearning/lib.php');
    }

    /**
     * Restore request data altered by the tests.
     *
     * @return void
     */
    public function tearDown(): void {
        $_POST = $this->originalpost;
        parent::tearDown();
    }

    /**
     * POST selection takes precedence and removes invalid and duplicate group ids.
     *
     * @return void
     */
    public function test_normalize_uses_post_selection_and_filters_ids(): void {
        $_POST = [
            'selectedboardgroups' => ['12', '0', '-4', 'invalid', '12', '34'],
        ];
        $data = (object) [
            'selectedboardgroups' => [98],
            'boardgroups' => '99',
            'usenumbers' => 1,
        ];

        kanbanlearning_normalize_board_group_settings($data);

        $this->assertSame('12,34', $data->boardgroups);
        $this->assertSame(12, $data->boardgroupid);
        $this->assertSame(1, $data->linknumbers);
    }

    /**
     * Parsed form selection is used when the custom POST field is absent.
     *
     * @return void
     */
    public function test_normalize_uses_parsed_selection_as_fallback(): void {
        $_POST = [];
        $data = (object) [
            'selectedboardgroups' => ['7', '7', '-2', 'invalid', '9'],
            'boardgroups' => '1,2',
            'usenumbers' => 1,
        ];

        kanbanlearning_normalize_board_group_settings($data);

        $this->assertSame('7,9', $data->boardgroups);
        $this->assertSame(7, $data->boardgroupid);
        $this->assertSame(1, $data->linknumbers);
    }

    /**
     * Serialized group ids accept comma and semicolon separators.
     *
     * @return void
     */
    public function test_normalize_accepts_serialized_group_ids(): void {
        $_POST = [];
        $data = (object) [
            'boardgroups' => ' 5;6,5;0;invalid;;',
            'usenumbers' => 1,
        ];

        kanbanlearning_normalize_board_group_settings($data);

        $this->assertSame('5,6', $data->boardgroups);
        $this->assertSame(5, $data->boardgroupid);
        $this->assertSame(1, $data->linknumbers);
    }

    /**
     * Invalid or empty selections are cleared and card number links follow the setting.
     *
     * @return void
     */
    public function test_normalize_clears_empty_selection_and_disables_number_links(): void {
        $_POST = [];
        $data = (object) [
            'selectedboardgroups' => [],
            'boardgroups' => '0,-2,invalid',
            'usenumbers' => 0,
        ];

        kanbanlearning_normalize_board_group_settings($data);

        $this->assertSame('', $data->boardgroups);
        $this->assertSame(0, $data->boardgroupid);
        $this->assertSame(0, $data->linknumbers);
    }
    /**
     * Supported activity features are reported as supported.
     *
     * @return void
     */
    public function test_supports_returns_true_for_supported_features(): void {
        $features = [
            FEATURE_IDNUMBER,
            FEATURE_GROUPS,
            FEATURE_GROUPINGS,
            FEATURE_MOD_INTRO,
            FEATURE_COMPLETION_TRACKS_VIEWS,
            FEATURE_COMPLETION_HAS_RULES,
            FEATURE_SHOW_DESCRIPTION,
            FEATURE_BACKUP_MOODLE2,
        ];

        foreach ($features as $feature) {
            $this->assertTrue(kanbanlearning_supports($feature), $feature);
        }
    }

    /**
     * Grade features that are not implemented are reported as unsupported.
     *
     * @return void
     */
    public function test_supports_returns_false_for_unsupported_grade_features(): void {
        $this->assertFalse(kanbanlearning_supports(FEATURE_GRADE_HAS_GRADE));
        $this->assertFalse(kanbanlearning_supports(FEATURE_GRADE_OUTCOMES));
    }

    /**
     * The activity reports its collaboration purpose.
     *
     * @return void
     */
    public function test_supports_returns_collaboration_purpose(): void {
        $this->assertSame(MOD_PURPOSE_COLLABORATION, kanbanlearning_supports(FEATURE_MOD_PURPOSE));
    }

    /**
     * Unknown features remain unknown to the module.
     *
     * @return void
     */
    public function test_supports_returns_null_for_unknown_feature(): void {
        $this->assertNull(kanbanlearning_supports('mod_kanbanlearning/unknown_feature'));
    }

    /**
     * Course module info includes the visible introduction and automatic completion rules.
     *
     * @return void
     */
    public function test_get_coursemodule_info_includes_description_and_completion_rules(): void {
        global $DB;

        $this->resetAfterTest();
        [, $module] = $this->create_activity_for_course_info();
        $DB->set_field('kanbanlearning', 'completioncreate', 1, ['id' => $module->id]);
        $DB->set_field('kanbanlearning', 'completioncomplete', 1, ['id' => $module->id]);

        $cm = get_coursemodule_from_id('kanbanlearning', $module->cmid, 0, false, MUST_EXIST);
        $cm->showdescription = 1;
        $cm->completion = COMPLETION_TRACKING_AUTOMATIC;

        $info = kanbanlearning_get_coursemodule_info($cm);

        $this->assertSame($module->name, $info->name);
        $this->assertStringContainsString('Course info intro', $info->content);
        $this->assertSame(1, (int) $info->customdata['customcompletionrules']['completioncreate']);
        $this->assertSame(1, (int) $info->customdata['customcompletionrules']['completioncomplete']);
    }

    /**
     * Course module info omits the introduction and completion rules when disabled.
     *
     * @return void
     */
    public function test_get_coursemodule_info_omits_hidden_description_and_disabled_completion(): void {
        $this->resetAfterTest();
        [, $module] = $this->create_activity_for_course_info();

        $cm = get_coursemodule_from_id('kanbanlearning', $module->cmid, 0, false, MUST_EXIST);
        $cm->showdescription = 0;
        $cm->completion = COMPLETION_TRACKING_NONE;

        $info = kanbanlearning_get_coursemodule_info($cm);

        $this->assertSame($module->name, $info->name);
        $this->assertEmpty($info->content ?? '');
        $this->assertEmpty($info->customdata['customcompletionrules'] ?? []);
    }

    /**
     * Teachers can rename cards and columns through the inplace editable callback.
     *
     * @return void
     */
    public function test_inplace_editable_updates_card_and_column_titles_for_teacher(): void {
        global $DB;

        $fixture = $this->create_inplace_editable_fixture();
        $this->setUser($fixture['teacher']);

        $cardeditable = kanbanlearning_inplace_editable('card', $fixture['cardid'], 'Updated card title');
        $columneditable = kanbanlearning_inplace_editable('column', $fixture['columnid'], 'Updated column title');

        $this->assertInstanceOf(\core\output\inplace_editable::class, $cardeditable);
        $this->assertInstanceOf(\core\output\inplace_editable::class, $columneditable);
        $this->assertSame('Updated card title', $DB->get_field('kanbanlearning_card', 'title', ['id' => $fixture['cardid']]));
        $this->assertSame('Updated column title', $DB->get_field('kanbanlearning_column', 'title', ['id' => $fixture['columnid']]));
    }

    /**
     * Students cannot rename cards they are not allowed to manage.
     *
     * @return void
     */
    public function test_inplace_editable_rejects_student_editing_unmanaged_card(): void {
        $fixture = $this->create_inplace_editable_fixture();
        $this->setUser($fixture['student']);

        $this->expectException(\moodle_exception::class);
        kanbanlearning_inplace_editable('card', $fixture['cardid'], 'Unauthorized title');
    }

    /**
     * Students cannot rename columns without the manage columns capability.
     *
     * @return void
     */
    public function test_inplace_editable_rejects_student_editing_column(): void {
        $fixture = $this->create_inplace_editable_fixture();
        $this->setUser($fixture['student']);

        $this->expectException(\required_capability_exception::class);
        kanbanlearning_inplace_editable('column', $fixture['columnid'], 'Unauthorized column title');
    }

    /**
     * Create an activity, board, card and enrolled teacher and student for inplace editable tests.
     *
     * @return array
     */
    private function create_inplace_editable_fixture(): array {
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

        return [
            'activity' => $activity,
            'boardmanager' => $boardmanager,
            'cardid' => $cardid,
            'columnid' => $columnid,
            'student' => $student,
            'teacher' => $teacher,
        ];
    }

    /**
     * Create the course and activity fixture used by course module info tests.
     *
     * @return array
     */
    private function create_activity_for_course_info(): array {
        $course = $this->getDataGenerator()->create_course([
            'shortname' => 'kanbanlearning_cm_info_' . bin2hex(random_bytes(8)),
        ]);
        $module = $this->getDataGenerator()->create_module('kanbanlearning', [
            'course' => $course,
            'name' => 'Course info test activity',
            'intro' => '<p>Course info intro</p>',
            'introformat' => FORMAT_HTML,
        ]);

        return [$course, $module];
    }
}
