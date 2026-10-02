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
 * @covers      \mod_kanbanlearning_generator
 */
final class generation_test extends \advanced_testcase {
    /**
     * Tests the data generator for this module
     *
     * @return void
     */
    public function test_create_instance(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $this->assertFalse($DB->record_exists('kanbanlearning', ['course' => $course->id]));
        $kanbanlearning = $this->getDataGenerator()->create_module('kanbanlearning', ['course' => $course]);

        $records = $DB->get_records('kanbanlearning', ['course' => $course->id], 'id');
        $this->assertCount(1, $records);
        $this->assertTrue(array_key_exists($kanbanlearning->id, $records));
    }
}
