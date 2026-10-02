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
 * Restore steps for mod_kanbanlearning
 *
 * @package     mod_kanbanlearning
 * @copyright   2023-2024 ISB Bayern
 * @author      Stefan Hanauska <stefan.hanauska@csg-in.de>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_kanbanlearning_activity_structure_step extends restore_activity_structure_step {
    /**
     * List of elements that can be restored
     *
     * @return array
     * @throws base_step_exception
     */
    protected function define_structure(): array {
        $paths = [];
        $paths[] = new restore_path_element('kanbanlearning', '/activity/kanbanlearning');
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('board', '/activity/kanbanlearning/boards/kanbanlearning_board');
        $paths[] = new restore_path_element('column', '/activity/kanbanlearning/boards/kanbanlearning_board/columns/kanbanlearning_column');
        $paths[] = new restore_path_element(
            'card',
            '/activity/kanbanlearning/boards/kanbanlearning_board/columns/kanbanlearning_column/cards/kanbanlearning_card'
        );

        if ($userinfo) {
            $paths[] = new restore_path_element(
                'assignee',
                '/activity/kanbanlearning/boards/kanbanlearning_board/columns/kanbanlearning_column'
                    . '/cards/kanbanlearning_card/assignees/kanbanlearning_assignee'
            );
            $paths[] = new restore_path_element(
                'discussion_comment',
                '/activity/kanbanlearning/boards/kanbanlearning_board/columns/kanbanlearning_column'
                    . '/cards/kanbanlearning_card/discussions/kanbanlearning_discussion_comment'
            );
            $paths[] = new restore_path_element(
                'historyitem',
                '/activity/kanbanlearning/boards/kanbanlearning_board/historyitems/kanbanlearning_history'
            );
        }

        return $this->prepare_activity_structure($paths);
    }

    /**
     * Restore a kanbanlearning record.
     *
     * @param array|object $data
     * @throws base_step_exception
     * @throws dml_exception
     * @throws restore_step_exception
     */
    protected function process_kanbanlearning($data): void {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();
        $includegroups = (bool)$this->get_setting_value('groups');
        $destinationgroups = [];
        if (!empty($data->boardmode) && (int)$data->boardmode === \mod_kanbanlearning\constants::MOD_KANBANLEARNING_BOARDMODE_GROUP) {
            $destinationgroups = groups_get_all_groups($this->get_courseid(), 0, 0, 'g.id, g.name');
        }
        if (!$includegroups) {
            $data->boardgroups = '';
            $data->boardgroupid = 0;
        } else if (!empty($data->boardgroups)) {
            $mappedgroupids = [];
            $groupids = preg_split('/[;,]/', (string)$data->boardgroups, -1, PREG_SPLIT_NO_EMPTY);
            foreach ($groupids as $groupid) {
                $mappedgroupid = $this->get_mappingid('group', (int)$groupid);
                if (!empty($mappedgroupid)) {
                    $mappedgroupids[] = (int)$mappedgroupid;
                }
            }
            $data->boardgroups = implode(',', array_unique($mappedgroupids));
        }
        if ((int)$data->boardmode === \mod_kanbanlearning\constants::MOD_KANBANLEARNING_BOARDMODE_GROUP && empty($destinationgroups)) {
            $data->boardmode = \mod_kanbanlearning\constants::MOD_KANBANLEARNING_BOARDMODE_SHARED;
            $data->boardgroups = '';
            $data->boardgroupid = 0;
        }

        $newid = $DB->insert_record('kanbanlearning', $data);
        $this->set_mapping('kanbanlearning_id', $oldid, $newid);
        $this->apply_activity_instance($newid);
    }

    /**
     * Restore a board record.
     *
     * @param array|object $data
     * @throws base_step_exception
     * @throws dml_exception
     * @throws restore_step_exception
     */
    protected function process_board($data): void {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;

        if ($this->get_setting_value('userinfo')) {
            $data->userid = $this->get_mappingid('user', $data->userid);
            $data->groupid = $this->get_mappingid('group', $data->groupid);
        } else {
            // The structural source becomes a reusable template in the destination course.
            $data->userid = 0;
            $data->groupid = 0;
            $data->template = 1;
        }
        $data->kanbanlearning_instance = $this->get_mappingid('kanbanlearning_id', $data->kanbanlearning_instance);

        $newid = $DB->insert_record('kanbanlearning_board', $data);
        $this->set_mapping('kanbanlearning_board_id', $oldid, $newid);
    }

    /**
     * Restore a column record.
     *
     * @param array|object $data
     * @throws base_step_exception
     * @throws dml_exception
     * @throws restore_step_exception
     */
    protected function process_column($data): void {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;

        $data->kanbanlearning_board = $this->get_mappingid('kanbanlearning_board_id', $data->kanbanlearning_board);

        $newid = $DB->insert_record('kanbanlearning_column', $data);
        $this->set_mapping('kanbanlearning_column_id', $oldid, $newid);
    }

    /**
     * Restore a card record.
     *
     * @param array|object $data
     * @throws base_step_exception
     * @throws dml_exception
     * @throws restore_step_exception
     */
    protected function process_card($data): void {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;

        $userinfo = $this->get_setting_value('userinfo');
        if (!$userinfo) {
            $data->discussion = 0;
        }

        $data->kanbanlearning_column = $this->get_mappingid('kanbanlearning_column_id', $data->kanbanlearning_column);
        $data->kanbanlearning_board = $this->get_mappingid('kanbanlearning_board_id', $data->kanbanlearning_board);
        $data->originalid = $this->get_mappingid('kanbanlearning_card_id', $data->originalid);
        $data->createdby = $this->get_mappingid('user', $data->createdby);

        if (empty($data->number)) {
            $data->number = $DB->get_field(
                'kanbanlearning_card',
                'MAX(number)',
                ['kanbanlearning_board' => $data->kanbanlearning_board]
            ) + 1;
        }

        $newid = $DB->insert_record('kanbanlearning_card', $data);
        $this->set_mapping('kanbanlearning_card_id', $oldid, $newid, true);
        $this->add_related_files('mod_kanbanlearning', 'attachments', 'kanbanlearning_card_id', null, $oldid);
    }

    /**
     * Restore an assignes record.
     *
     * @param array|object $data
     * @throws base_step_exception
     * @throws dml_exception
     * @throws restore_step_exception
     */
    protected function process_assignee($data): void {
        global $DB;

        $data = (object) $data;

        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->kanbanlearning_card = $this->get_mappingid('kanbanlearning_card_id', $data->kanbanlearning_card);

        $DB->insert_record('kanbanlearning_assignee', $data);
    }

    /**
     * Restore an historyitem record.
     *
     * @param array|object $data
     * @throws base_step_exception
     * @throws dml_exception
     * @throws restore_step_exception
     */
    protected function process_historyitem($data): void {
        global $DB;

        $data = (object) $data;

        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->kanbanlearning_card = $this->get_mappingid('kanbanlearning_card_id', $data->kanbanlearning_card);
        $data->kanbanlearning_column = $this->get_mappingid('kanbanlearning_column_id', $data->kanbanlearning_column);
        $data->kanbanlearning_board = $this->get_mappingid('kanbanlearning_board_id', $data->kanbanlearning_board);
        $data->affected_userid = $this->get_mappingid('user', $data->affected_userid);

        $DB->insert_record('kanbanlearning_history', $data);
    }

    /**
     * Restore an discussion_comment record.
     *
     * @param array|object $data
     * @throws base_step_exception
     * @throws dml_exception
     * @throws restore_step_exception
     */
    protected function process_discussion_comment($data): void {
        global $DB;

        $data = (object) $data;

        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->kanbanlearning_card = $this->get_mappingid('kanbanlearning_card_id', $data->kanbanlearning_card);

        $DB->insert_record('kanbanlearning_comment', $data);
    }

    /**
     * Extra actions to take once restore is complete.
     */
    protected function after_execute(): void {
        global $DB;
        $this->add_related_files('mod_kanbanlearning', 'intro', null);

        $kanbanlearningboards = $DB->get_records('kanbanlearning_board', ['kanbanlearning_instance' => $this->task->get_activityid()]);

        foreach ($kanbanlearningboards as $board) {
            if ($board->sequence == '') {
                continue;
            }
            $seq = explode(',', $board->sequence);
            foreach ($seq as $key => $columnid) {
                $seq[$key] = $this->get_mappingid('kanbanlearning_column_id', $columnid);
            }
            $DB->update_record('kanbanlearning_board', ['id' => $board->id, 'sequence' => join(',', $seq)]);
            mod_kanbanlearning\helper::update_cached_board($board->id);

            $kanbanlearningcolumns = $DB->get_records('kanbanlearning_column', ['kanbanlearning_board' => $board->id]);

            foreach ($kanbanlearningcolumns as $column) {
                if (!$this->get_setting_value('userinfo')) {
                    // Card IDs are not restored without user data.
                    $DB->set_field('kanbanlearning_column', 'sequence', '', ['id' => $column->id]);
                    continue;
                }
                if ($column->sequence == '') {
                    continue;
                }
                $seqcard = explode(',', $column->sequence);
                foreach ($seqcard as $cardkey => $cardid) {
                    $seqcard[$cardkey] = $this->get_mappingid('kanbanlearning_card_id', $cardid);
                }
                $DB->update_record('kanbanlearning_column', ['id' => $column->id, 'sequence' => join(',', $seqcard)]);
            }
        }
    }
}
