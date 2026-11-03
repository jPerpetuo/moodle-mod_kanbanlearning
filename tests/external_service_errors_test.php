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
 * PHPUnit coverage for external service error paths.
 *
 * @package     mod_kanbanlearning
 * @copyright   2026 Joao Ariel Perpetuo
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \mod_kanbanlearning\external\change_kanbanlearning_content
 * @runTestsInSeparateProcesses
 */
final class external_service_errors_test extends \advanced_testcase {
    /** @var \stdClass Course used for testing. */
    private $course;

    /** @var \stdClass Kanban activity used for testing. */
    private $kanbanlearning;

    /** @var array Test users. */
    private $users;

    /**
     * Prepare the testing environment.
     *
     * @return void
     */
    public function setUp(): void {
        global $DB, $SCRIPT;

        parent::setUp();
        $this->course = $this->getDataGenerator()->create_course();
        $this->kanbanlearning = $this->getDataGenerator()->create_module(
            'kanbanlearning',
            ['course' => $this->course]
        );

        for ($i = 0; $i < 3; $i++) {
            $this->users[$i] = $this->getDataGenerator()->create_user([
                'email' => $i . 'user@example.com',
                'username' => 'userid' . $i,
            ]);
        }

        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        $teacherrole = $DB->get_record('role', ['shortname' => 'editingteacher']);
        $this->getDataGenerator()->enrol_user($this->users[0]->id, $this->course->id, $studentrole->id);
        $this->getDataGenerator()->enrol_user($this->users[1]->id, $this->course->id, $studentrole->id);
        $this->getDataGenerator()->enrol_user($this->users[2]->id, $this->course->id, $teacherrole->id);
        $SCRIPT = '/mod/kanbanlearning/view.php';
    }

    /**
     * External card movement rejects missing required parameters.
     *
     * @return void
     */
    public function test_move_card_rejects_missing_parameter(): void {
        global $CFG;
        require_once($CFG->dirroot . '/lib/externallib.php');

        $this->resetAfterTest();
        $this->setUser($this->users[2]);
        $manager = new \mod_kanbanlearning\boardmanager($this->kanbanlearning->cmid);
        $boardid = $manager->create_board();

        $this->expectException(\invalid_parameter_exception::class);
        \mod_kanbanlearning\external\change_kanbanlearning_content::move_card(
            $this->kanbanlearning->cmid,
            $boardid,
            ['cardid' => 1, 'columnid' => 1]
        );
    }

    /**
     * Students cannot add columns through the external service.
     *
     * @return void
     */
    public function test_add_column_requires_capability(): void {
        global $CFG;
        require_once($CFG->dirroot . '/lib/externallib.php');

        $this->resetAfterTest();
        $this->setUser($this->users[2]);
        $manager = new \mod_kanbanlearning\boardmanager($this->kanbanlearning->cmid);
        $boardid = $manager->create_board();
        $this->setUser($this->users[0]);

        $this->expectException(\required_capability_exception::class);
        \mod_kanbanlearning\external\change_kanbanlearning_content::add_column(
            $this->kanbanlearning->cmid,
            $boardid,
            ['aftercol' => 0, 'title' => 'Student column']
        );
    }

    /**
     * External card movement rejects a card that does not exist.
     *
     * @return void
     */
    public function test_move_card_rejects_missing_card(): void {
        global $CFG;
        require_once($CFG->dirroot . '/lib/externallib.php');

        $this->resetAfterTest();
        $this->setUser($this->users[2]);
        $manager = new \mod_kanbanlearning\boardmanager($this->kanbanlearning->cmid);
        $boardid = $manager->create_board();

        $this->expectException(\dml_missing_record_exception::class);
        \mod_kanbanlearning\external\change_kanbanlearning_content::move_card(
            $this->kanbanlearning->cmid,
            $boardid,
            ['cardid' => PHP_INT_MAX, 'aftercard' => 0, 'columnid' => 1]
        );
    }

    /**
     * Moving a card into a locked non-completion column leaves it in place.
     *
     * @return void
     */
    public function test_move_card_into_locked_column_is_rejected(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/lib/externallib.php');

        $this->resetAfterTest();
        $this->setUser($this->users[2]);
        $manager = new \mod_kanbanlearning\boardmanager($this->kanbanlearning->cmid);
        $boardid = $manager->create_board();
        $manager->load_board($boardid);
        $columnids = array_map('intval', explode(',', $manager->get_board()->sequence));
        $sourcecolumnid = $columnids[0];
        $targetcolumnid = $columnids[1];
        $cardid = $manager->add_card($sourcecolumnid, 0, ['title' => 'Card remains in source']);
        $DB->set_field('kanbanlearning_column', 'locked', 1, ['id' => $targetcolumnid]);

        $returnvalue = \mod_kanbanlearning\external\change_kanbanlearning_content::move_card(
            $this->kanbanlearning->cmid,
            $boardid,
            ['cardid' => $cardid, 'aftercard' => 0, 'columnid' => $targetcolumnid]
        );
        $returnvalue = \external_api::clean_returnvalue(
            \mod_kanbanlearning\external\change_kanbanlearning_content::move_card_returns(),
            $returnvalue
        );
        $update = json_decode($returnvalue['update'], true);

        $this->assertEquals(
            $sourcecolumnid,
            $DB->get_field('kanbanlearning_card', 'kanbanlearning_column', ['id' => $cardid])
        );
        $this->assertCount(1, $update);
        $this->assertSame('columns', $update[0]['name']);
        $this->assertEquals($targetcolumnid, $update[0]['fields']['id']);
        $this->assertEquals(1, $update[0]['fields']['locked']);
    }

    /**
     * Moving an assigned card into a column over its WIP limit is rejected without mutation.
     *
     * @return void
     */
    public function test_move_card_respects_wip_limit(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/lib/externallib.php');

        $this->resetAfterTest();
        $this->setUser($this->users[2]);
        $manager = new \mod_kanbanlearning\boardmanager($this->kanbanlearning->cmid);
        $boardid = $manager->create_board();
        $manager->load_board($boardid);
        $columnids = array_map('intval', explode(',', $manager->get_board()->sequence));
        $sourcecolumnid = $columnids[0];
        $targetcolumnid = $columnids[1];
        $movingcardid = $manager->add_card($sourcecolumnid, 0, ['title' => 'Card to move']);
        $existingcardid = $manager->add_card($targetcolumnid, 0, ['title' => 'Card at WIP limit']);
        $manager->assign_user($existingcardid, $this->users[0]->id);
        $manager->assign_user($movingcardid, $this->users[0]->id);
        $DB->set_field(
            'kanbanlearning_column',
            'options',
            json_encode(['wiplimit' => 1]),
            ['id' => $targetcolumnid]
        );

        try {
            \mod_kanbanlearning\external\change_kanbanlearning_content::move_card(
                $this->kanbanlearning->cmid,
                $boardid,
                ['cardid' => $movingcardid, 'aftercard' => 0, 'columnid' => $targetcolumnid]
            );
            $this->fail('The move should fail when it exceeds the target column WIP limit.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('wiplimitreached', $exception->errorcode);
        }

        $this->assertEquals(
            $sourcecolumnid,
            $DB->get_field('kanbanlearning_card', 'kanbanlearning_column', ['id' => $movingcardid])
        );
        $this->assertSame(
            (string)$existingcardid,
            $DB->get_field('kanbanlearning_column', 'sequence', ['id' => $targetcolumnid])
        );
    }
}
