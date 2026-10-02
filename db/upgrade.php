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

/**
 * mod_kanbanlearning db upgrades.
 *
 * @package    mod_kanbanlearning
 * @copyright  2023-2024 ISB Bayern
 * @author     Stefan Hanauska
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Define upgrade steps to be performed to upgrade the plugin from the old version to the current one.
 *
 * @param int $oldversion Version number the plugin is being upgraded from.
 */
function xmldb_kanbanlearning_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2024121602) {
        // Define field repeat_enable to be added to kanbanlearning_card.
        $table = new xmldb_table('kanbanlearning_card');
        $field = new xmldb_field('repeat_enable', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0', 'timemodified');

        // Conditionally launch add field repeat_enable.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('repeat_interval', XMLDB_TYPE_INTEGER, '11', null, XMLDB_NOTNULL, null, '1', 'repeat_enable');

        // Conditionally launch add field repeat_interval.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field(
            'repeat_interval_type',
            XMLDB_TYPE_INTEGER,
            '11',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'repeat_interval'
        );

        // Conditionally launch add field repeat_interval_type.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field(
            'repeat_newduedate',
            XMLDB_TYPE_INTEGER,
            '5',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'repeat_interval_type'
        );

        // Conditionally launch add field repeat_newduedate.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Kanban savepoint reached.
        upgrade_mod_savepoint(true, 2024121602, 'kanbanlearning');
    }

    if ($oldversion < 2025020301) {
        // Define field usenumbers to be added to kanbanlearning.
        $table = new xmldb_table('kanbanlearning');
        $field = new xmldb_field('usenumbers', XMLDB_TYPE_INTEGER, '2', null, null, null, '0', 'history');

        // Conditionally launch add field id.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field linknumbers to be added to table kanbanlearning.
        $field = new xmldb_field('linknumbers', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0', 'usenumbers');

        // Conditionally launch add field linknumbers.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field number to be added to table kanbanlearning_card.
        $table = new xmldb_table('kanbanlearning_card');
        $field = new xmldb_field('number', XMLDB_TYPE_INTEGER, '10', null, null, null, '0', 'timemodified');

        // Conditionally launch add field id.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Set numbers for all cards.
        $board = 0;
        $nextnumber = 0;
        $cards = $DB->get_recordset('kanbanlearning_card', ['number' => 0], 'kanbanlearning_board ASC, timecreated ASC');
        foreach ($cards as $card) {
            if ($card->kanbanlearning_board != $board) {
                $board = $card->kanbanlearning_board;
                $nextnumber = $DB->get_field('kanbanlearning_card', 'MAX(number)', ['kanbanlearning_board' => $board]) + 1;
            } else {
                $nextnumber++;
            }
            $DB->set_field('kanbanlearning_card', 'number', $nextnumber, ['id' => $card->id]);
        }
        $cards->close();

        // Kanban savepoint reached.
        upgrade_mod_savepoint(true, 2025020301, 'kanbanlearning');
    }

    if ($oldversion < 2026050701) {
        // Define field boardmode to be added to kanbanlearning.
        $table = new xmldb_table('kanbanlearning');
        $field = new xmldb_field('boardmode', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0', 'introformat');

        // Conditionally launch add field boardmode.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Kanban savepoint reached.
        upgrade_mod_savepoint(true, 2026050701, 'kanbanlearning');
    }

    if ($oldversion < 2026050702) {
        // Define field boardgroupid to be added to kanbanlearning.
        $table = new xmldb_table('kanbanlearning');
        $field = new xmldb_field('boardgroupid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'boardmode');

        // Conditionally launch add field boardgroupid.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Kanban savepoint reached.
        upgrade_mod_savepoint(true, 2026050702, 'kanbanlearning');
    }

    if ($oldversion < 2026051502) {
        // Define field boardgroups to be added to kanbanlearning.
        $table = new xmldb_table('kanbanlearning');
        $field = new xmldb_field('boardgroups', XMLDB_TYPE_TEXT, null, null, null, null, null, 'boardgroupid');

        // Conditionally launch add field boardgroups.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Kanban savepoint reached.
        upgrade_mod_savepoint(true, 2026051502, 'kanbanlearning');
    }

    if ($oldversion < 2026090400) {
        // Rename the discussion comments table to stay within Moodle 4.1's table-name limit.
        $table = new xmldb_table('kanbanlearning_discussion_comment');
        if ($dbman->table_exists($table)) {
            $dbman->rename_table($table, 'kanbanlearning_comment');
        }

        upgrade_mod_savepoint(true, 2026090400, 'kanbanlearning');
    }

    if ($oldversion < 2026093000) {
        $table = new xmldb_table('kanbanlearning');
        $field = new xmldb_field('approval_seals', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0', 'linknumbers');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $table = new xmldb_table('kanbanlearning_card');
        $field = new xmldb_field('approval_seal', XMLDB_TYPE_CHAR, '32', null, null, null, null, 'number');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        } else {
            $dbman->change_field_notnull($table, $field);
            $dbman->change_field_default($table, $field);
        }

        upgrade_mod_savepoint(true, 2026093000, 'kanbanlearning');
    }

    return true;
}
