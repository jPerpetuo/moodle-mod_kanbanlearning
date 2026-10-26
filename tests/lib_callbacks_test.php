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
}
