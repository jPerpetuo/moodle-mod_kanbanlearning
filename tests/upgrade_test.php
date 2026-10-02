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
 * Tests for Kanban database upgrades.
 *
 * @package     mod_kanbanlearning
 * @copyright   2026 CCEAD PUC-Rio
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      ::xmldb_kanbanlearning_upgrade
 */
final class upgrade_test extends \advanced_testcase {
    /**
     * Load the plugin upgrade steps.
     *
     * @return void
     */
    public function setUp(): void {
        global $CFG;

        parent::setUp();
        require_once($CFG->libdir . '/upgradelib.php');
        require_once($CFG->dirroot . '/mod/kanbanlearning/db/upgrade.php');
    }

    /**
     * Upgrade preserves an existing activity while adding the group board fields.
     *
     * @return void
     */
    public function test_upgrade_adds_group_board_fields_without_losing_activity_data(): void {
        global $DB;

        $this->resetAfterTest();
        $this->preventResetByRollback();

        $course = $this->getDataGenerator()->create_course();
        $kanbanlearning = $this->getDataGenerator()->create_module('kanbanlearning', [
            'course' => $course,
            'name' => 'Legacy group board activity',
            'history' => 1,
        ]);
        $dbman = $DB->get_manager();
        $table = new \xmldb_table('kanbanlearning');

        $boardgroups = new \xmldb_field('boardgroups', XMLDB_TYPE_TEXT, null, null, null, null, null, 'boardgroupid');
        $boardgroupid = new \xmldb_field('boardgroupid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'boardmode');
        $dbman->drop_field($table, $boardgroups);
        $dbman->drop_field($table, $boardgroupid);

        $this->run_upgrade_from_version(2026050701);

        $this->assertTrue($dbman->field_exists($table, $boardgroupid));
        $this->assertTrue($dbman->field_exists($table, $boardgroups));

        $upgraded = $DB->get_record('kanbanlearning', ['id' => $kanbanlearning->id], '*', MUST_EXIST);
        $this->assertEquals('Legacy group board activity', $upgraded->name);
        $this->assertEquals(1, (int) $upgraded->history);
        $this->assertNull($upgraded->boardgroupid);
        $this->assertNull($upgraded->boardgroups);
    }

    /**
     * Upgrade assigns sequential card numbers to cards created before numbering was available.
     *
     * @return void
     */
    public function test_upgrade_assigns_numbers_to_legacy_cards(): void {
        global $DB;

        $this->resetAfterTest();
        $this->preventResetByRollback();

        $course = $this->getDataGenerator()->create_course();
        $kanbanlearning = $this->getDataGenerator()->create_module('kanbanlearning', ['course' => $course]);
        $boardmanager = new boardmanager($kanbanlearning->cmid);
        $boardid = $boardmanager->create_board();
        $boardmanager->load_board($boardid);
        $columnid = $DB->get_field('kanbanlearning_column', 'id', ['kanbanlearning_board' => $boardid], IGNORE_MULTIPLE);
        $firstcardid = $boardmanager->add_card($columnid, 0, ['title' => 'First legacy card']);
        $secondcardid = $boardmanager->add_card($columnid, $firstcardid, ['title' => 'Second legacy card']);
        $DB->set_field('kanbanlearning_card', 'number', 0, ['id' => $firstcardid]);
        $DB->set_field('kanbanlearning_card', 'number', 0, ['id' => $secondcardid]);

        $dbman = $DB->get_manager();
        $table = new \xmldb_table('kanbanlearning_card');
        $number = new \xmldb_field('number', XMLDB_TYPE_INTEGER, '10', null, null, null, '0', 'timemodified');
        $dbman->drop_field($table, $number);

        $this->run_upgrade_from_version(2024121602);

        $this->assertTrue($dbman->field_exists($table, $number));
        $firstcard = $DB->get_record('kanbanlearning_card', ['id' => $firstcardid], 'id, number', MUST_EXIST);
        $secondcard = $DB->get_record('kanbanlearning_card', ['id' => $secondcardid], 'id, number', MUST_EXIST);
        $this->assertEquals(1, (int) $firstcard->number);
        $this->assertEquals(2, (int) $secondcard->number);
    }

    /**
     * Upgrade renames discussion comments without losing their data.
     *
     * @return void
     */
    public function test_upgrade_renames_discussion_comments_table(): void {
        global $CFG, $DB;

        $this->resetAfterTest();
        $this->preventResetByRollback();

        // The old table name could only exist on Moodle versions with the larger name limit.
        if ($CFG->version < 2024042200) {
            $this->markTestSkipped('The legacy table name cannot exist on this Moodle version.');
        }

        $dbman = $DB->get_manager();
        $newtable = new \xmldb_table('kanbanlearning_comment');
        if ($dbman->table_exists($newtable)) {
            $dbman->drop_table($newtable);
        }

        $oldtable = new \xmldb_table('kanbanlearning_discussion_comment');
        $oldtable->addField(new \xmldb_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE));
        $oldtable->addField(new \xmldb_field('content', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL));
        $oldtable->addKey(new \xmldb_key('primary', XMLDB_KEY_PRIMARY, ['id']));
        $dbman->create_table($oldtable);
        $commentid = $DB->insert_record('kanbanlearning_discussion_comment', ['content' => 'Legacy comment']);

        $this->run_upgrade_from_version(2026051502);

        $this->assertFalse($dbman->table_exists($oldtable));
        $this->assertTrue($dbman->table_exists($newtable));
        $comment = $DB->get_record('kanbanlearning_comment', ['id' => $commentid], '*', MUST_EXIST);
        $this->assertEquals('Legacy comment', $comment->content);
    }

    /**
     * Upgrade adds the optional teacher seal fields with safe defaults.
     * @return void
     */
    public function test_upgrade_adds_approval_seal_fields(): void {
        global $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback();
        $course = $this->getDataGenerator()->create_course();
        $activity = $this->getDataGenerator()->create_module('kanbanlearning', ['course' => $course]);
        $boardmanager = new boardmanager($activity->cmid);
        $boardid = $boardmanager->create_board();
        $boardmanager->load_board($boardid);
        $columnid = $DB->get_field('kanbanlearning_column', 'id', ['kanbanlearning_board' => $boardid], IGNORE_MULTIPLE);
        $cardid = $boardmanager->add_card($columnid, 0, ['title' => 'Card without a reaction']);
        $dbman = $DB->get_manager();
        $activitytable = new \xmldb_table('kanbanlearning');
        $cardtable = new \xmldb_table('kanbanlearning_card');
        $activityfield = new \xmldb_field(
            'approval_seals',
            XMLDB_TYPE_INTEGER,
            '2',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'linknumbers'
        );
        $cardfield = new \xmldb_field('approval_seal', XMLDB_TYPE_CHAR, '32', null, null, null, null, 'number');
        $dbman->drop_field($activitytable, $activityfield);
        $dbman->drop_field($cardtable, $cardfield);

        $this->run_upgrade_from_version(2026092900);

        $this->assertTrue($dbman->field_exists($activitytable, $activityfield));
        $this->assertTrue($dbman->field_exists($cardtable, $cardfield));
        $this->assertEquals(0, (int)$DB->get_field('kanbanlearning', 'approval_seals', ['id' => $activity->id]));
        $card = $DB->get_record('kanbanlearning_card', ['id' => $cardid], '*', MUST_EXIST);
        $this->assertEmpty($card->approval_seal);
    }

    /**
     * Run an upgrade while simulating the version installed before the upgrade.
     *
     * @param int $oldversion The version stored before the upgrade.
     * @return void
     */
    private function run_upgrade_from_version(int $oldversion): void {
        $installedversion = get_config('mod_kanbanlearning', 'version');
        set_config('version', $oldversion, 'mod_kanbanlearning');

        try {
            \xmldb_kanbanlearning_upgrade($oldversion);
        } finally {
            set_config('version', $installedversion, 'mod_kanbanlearning');
        }
    }
}
