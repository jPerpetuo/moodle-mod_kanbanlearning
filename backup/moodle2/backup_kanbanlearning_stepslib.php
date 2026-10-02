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
 * Backup steps for mod_kanbanlearning
 *
 * @package     mod_kanbanlearning
 * @copyright   2023-2024 ISB Bayern
 * @author      Stefan Hanauska <stefan.hanauska@csg-in.de>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_kanbanlearning_activity_structure_step extends backup_activity_structure_step {
    /**
     * Defines the XML structure for kanbanlearning backups
     *
     * @return backup_nested_element
     */
    protected function define_structure(): backup_nested_element {
        $userinfo = $this->get_setting_value('userinfo');

        $kanbanlearning = new backup_nested_element(
            'kanbanlearning',
            ['id'],
            [
                'course',
                'name',
                'intro',
                'introformat',
                'boardmode',
                'boardgroupid',
                'boardgroups',
                'userboards',
                'history',
                'approval_seals',
                'completioncreate',
                'completioncomplete',
                'repeat_enable',
                'repeat_interval',
                'repeat_interval_type',
                'repeat_newduedate',
            ]
        );
        $kanbanlearning->set_source_table('kanbanlearning', ['id' => backup::VAR_ACTIVITYID]);
        $kanbanlearning->annotate_files('mod_kanbanlearning', 'intro', null);

        $boards = new backup_nested_element('boards');
        $board = new backup_nested_element(
            'kanbanlearning_board',
            ['id'],
            [
                'sequence', 'timecreated', 'timemodified', 'userid', 'groupid',
                'template', 'kanbanlearning_instance', 'options', 'locked',
            ]
        );

        $columns = new backup_nested_element('columns');
        $column = new backup_nested_element(
            'kanbanlearning_column',
            ['id'],
            ['title', 'sequence', 'timecreated', 'timemodified', 'kanbanlearning_board', 'options', 'locked']
        );

        $cards = new backup_nested_element('cards');
        $card = new backup_nested_element(
            'kanbanlearning_card',
            ['id'],
            [
                'title',
                'timecreated',
                'timemodified',
                'kanbanlearning_board',
                'kanbanlearning_column',
                'options',
                'duedate',
                'reminderdate',
                'completed',
                'description',
                'descriptionformat',
                'linkedactivity',
                'originalid',
                'discussion',
                'reminder_sent',
                'createdby',
                'approval_seal',
            ]
        );
        $card->annotate_files('mod_kanbanlearning', 'attachments', 'id');
        $card->annotate_ids('kanbanlearning_card_id', 'originalid');

        $assignees = new backup_nested_element('assignees');
        $assignee = new backup_nested_element(
            'kanbanlearning_assignee',
            ['id'],
            ['kanbanlearning_card', 'userid']
        );

        $discussions = new backup_nested_element('discussions');
        $discussion = new backup_nested_element(
            'kanbanlearning_discussion_comment',
            ['id'],
            ['kanbanlearning_card', 'userid', 'timecreated', 'content']
        );

        $historyitems = new backup_nested_element('historyitems');
        $historyitem = new backup_nested_element(
            'kanbanlearning_history',
            ['id'],
            [
                'userid',
                'kanbanlearning_board',
                'kanbanlearning_column',
                'kanbanlearning_card',
                'action',
                'parameters',
                'timestamp',
                'affected_userid',
                'type',
            ]
        );

        $kanbanlearning->add_child($boards);
        $boards->add_child($board);
        $board->add_child($columns);
        $columns->add_child($column);
        $column->add_child($cards);
        $cards->add_child($card);
        $card->add_child($assignees);
        $assignees->add_child($assignee);
        $card->add_child($discussions);
        $discussions->add_child($discussion);
        $board->add_child($historyitems);
        $historyitems->add_child($historyitem);

        if ($userinfo) {
            $board->set_source_table('kanbanlearning_board', ['kanbanlearning_instance' => backup::VAR_PARENTID]);
            $board->annotate_ids('userid', 'userid');
            $board->annotate_ids('groupid', 'groupid');
            $assignee->set_source_table('kanbanlearning_assignee', ['kanbanlearning_card' => backup::VAR_PARENTID]);
            $assignee->annotate_ids('userid', 'userid');
            $assignee->annotate_ids('kanbanlearning_card_id', 'kanbanlearning_card');
            $card->annotate_ids('userid', 'createdby');
            $discussion->set_source_table('kanbanlearning_comment', ['kanbanlearning_card' => backup::VAR_PARENTID]);
            $discussion->annotate_ids('userid', 'userid');
            $discussion->annotate_ids('kanbanlearning_card_id', 'kanbanlearning_card');
            $historyitem->set_source_table('kanbanlearning_history', ['kanbanlearning_board' => backup::VAR_PARENTID]);
            $historyitem->annotate_ids('userid', 'userid');
            $historyitem->annotate_ids('userid', 'affected_userid');
            $historyitem->annotate_ids('kanbanlearning_card_id', 'kanbanlearning_card');
            $historyitem->annotate_ids('kanbanlearning_column_id', 'kanbanlearning_column');
            $historyitem->annotate_ids('kanbanlearning_board_id', 'kanbanlearning_board');
        } else {
            $structureboardid = $this->get_structure_source_board_id();
            // A source is mandatory even when there is no existing board to copy.
            $board->set_source_table('kanbanlearning_board', ['id' => ['sqlparam' => $structureboardid]]);
        }
        $column->set_source_table('kanbanlearning_column', ['kanbanlearning_board' => backup::VAR_PARENTID]);

        if ($userinfo) {
            $card->set_source_table('kanbanlearning_card', ['kanbanlearning_column' => backup::VAR_PARENTID]);
        } else {
            // Keep the XML element valid without exporting card content.
            $card->set_source_table('kanbanlearning_card', ['id' => ['sqlparam' => 0]]);
        }

        $board->annotate_ids('kanbanlearning_id', 'kanbanlearning_instance');
        $column->annotate_ids('kanbanlearning_board_id', 'kanbanlearning_board');
        $card->annotate_ids('kanbanlearning_board_id', 'kanbanlearning_board');
        $card->annotate_ids('kanbanlearning_column_id', 'kanbanlearning_column');

        return $this->prepare_activity_structure($kanbanlearning);
    }

    /**
     * Get the board that supplies structure when user data is excluded.
     *
     * @return int
     */
    private function get_structure_source_board_id(): int {
        global $DB;

        $kanbanlearningid = $this->task->get_activityid();
        $kanbanlearning = $DB->get_record('kanbanlearning', ['id' => $kanbanlearningid], 'boardmode, boardgroupid', IGNORE_MISSING);
        if (!$kanbanlearning) {
            return 0;
        }

        if ((int)$kanbanlearning->boardmode === \mod_kanbanlearning\constants::MOD_KANBANLEARNING_BOARDMODE_GROUP) {
            if ($kanbanlearning->boardgroupid) {
                $groupboardid = $DB->get_field('kanbanlearning_board', 'id', [
                    'kanbanlearning_instance' => $kanbanlearningid,
                    'userid' => 0,
                    'groupid' => $kanbanlearning->boardgroupid,
                    'template' => 0,
                ]);
                if ($groupboardid) {
                    return (int)$groupboardid;
                }
            }

            $groupboardid = $DB->get_field_sql(
                'SELECT MIN(id)
                   FROM {kanbanlearning_board}
                  WHERE kanbanlearning_instance = :instance
                    AND userid = :userid
                    AND groupid > :groupid
                    AND template = :template',
                ['instance' => $kanbanlearningid, 'userid' => 0, 'groupid' => 0, 'template' => 0]
            );
            if ($groupboardid) {
                return (int)$groupboardid;
            }
        } else {
            $courseboardid = $DB->get_field('kanbanlearning_board', 'id', [
                'kanbanlearning_instance' => $kanbanlearningid,
                'userid' => 0,
                'groupid' => 0,
                'template' => 0,
            ]);
            if ($courseboardid) {
                return (int)$courseboardid;
            }
        }

        return (int)$DB->get_field_sql(
            'SELECT id
               FROM {kanbanlearning_board}
              WHERE kanbanlearning_instance = :instance AND template = :template
              ORDER BY timemodified DESC',
            ['instance' => $kanbanlearningid, 'template' => 1],
            IGNORE_MISSING
        );
    }
}
