// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see http://www.gnu.org/licenses/.

/**
 * Selectors for mod_kanbanlearning.
 * @module mod_kanbanlearning/selectors
 * @copyright 2024 ISB Bayern
 * @author Stefan Hanauska stefan.hanauska@csg-in.de
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
export default {
    ADDCARD: `[data-action="add_card"]`,
    ADDCARDCONTAINER: `.mod_kanbanlearning_addcard_container`,
    ADDCARDFIRST: `.mod_kanbanlearning_addcard_first`,
    ADDCOLUMN: `[data-action="add_column"]`,
    APPROVALSEAL: `[data-action="approval_seal"]`,
    APPROVALSEALTOGGLE: `[data-action="toggle_approval_seal"]`,
    APPROVALSEALREMOVE: `[data-action="remove_approval_seal"]`,
    ADDCOLUMNCONTAINER: `.mod_kanbanlearning_addcolumn_container`,
    ADDCOLUMNFIRST: `.mod_kanbanlearning_addcolumn_first [data-action="add_column"]`,
    ASSIGNEES: `.mod_kanbanlearning_assignees`,
    ASSIGNSELF: `[data-action="assign_self"]`,
    ASSIGNUSER: `[data-action="assign_user"]`,
    ASSIGNEDUSER: `.mod_kanbanlearning_assigned_user`,
    BOARD: `.mod_kanbanlearning_board`,
    CARD: `.mod_kanbanlearning_card`,
    CARDCOUNT: `.mod_kanbanlearning_cardcount`,
    CARDNUMBER: `.mod_kanbanlearning_card_number`,
    COLUMN: `.mod_kanbanlearning_column`,
    COLUMNCONTAINER: `.mod_kanbanlearning_column_container`,
    COLUMNINNER: `.mod_kanbanlearning_column_inner`,
    COMPLETE: `[data-action="complete_card"]`,
    COMPLETIONSTATE: `.mod_kanbanlearning_card_completion`,
    COMPLETIONINDICATOR: `.mod_kanbanlearning_card_completion_indicator`,
    CONTAINER: `.mod_kanbanlearning_render_container`,
    DELETEBOARD: `[data-action="delete_board"]`,
    DELETECARD: `[data-action="delete_card"]`,
    DELETECOLUMN: `[data-action="delete_column"]`,
    DELETEMESSAGE: `[data-action="delete_message"]`,
    DESCRIPTIONMODAL: `.mod_kanbanlearning_description`,
    DESCRIPTIONMODALBODY: `.mod_kanbanlearning_description_modal .modal-body`,
    DESCRIPTIONMODALFOOTER: `.mod_kanbanlearning_description_modal .modal-footer`,
    DESCRIPTIONMODALTITLE: `.mod_kanbanlearning_description_modal .modal-title`,
    DESCRIPTIONTOGGLE: `.mod_kanbanlearning_description`,
    DETAILBUTTON: `.mod_kanbanlearning_detail_trigger`,
    DISCUSSION: `.mod_kanbanlearning_discussion`,
    DISCUSSIONINPUT: `.mod_kanbanlearning_discussion_input`,
    DISCUSSIONMESSAGES: `.mod_kanbanlearning_discussion_messages`,
    DISCUSSIONMODAL: `.mod_kanbanlearning_discussion_modal`,
    DISCUSSIONMODALTITLE: `.mod_kanbanlearning_discussion_modal .modal-title`,
    DISCUSSIONMODALTRIGGER: `.mod_kanbanlearning_discussion_trigger`,
    DISCUSSIONSEND: `[data-action="send_discussion_message"]`,
    DISCUSSIONSHOW: `[data-action="show_discussion"]`,
    DUEDATE: `.mod_kanbanlearning_duedate`,
    DUPLICATE: `[data-action="duplicate_card"]`,
    EDITDETAILS: `[data-action="edit_details"]`,
    HIDEHIDDEN: `[data-action="hide_hidden"]`,
    HISTORY: `.mod_kanbanlearning_history`,
    HISTORYITEMS: `.mod_kanbanlearning_history_items`,
    HISTORYMODAL: `.mod_kanbanlearning_history_modal`,
    HISTORYMODALTRIGGER: `[data-action="show_history"]`,
    INPLACEEDITABLE: `.inplaceeditable`,
    LOCKCOLUMN: `[data-action="lock_column"]`,
    LOCKBOARDCOLUMNS: `[data-action="lock_board_columns"]`,
    MAIN: `.mod_kanbanlearning_main`,
    MOVECARDAFTERCARD: `.mod_kanbanlearning_move_card_aftercard`,
    MOVECARDCOLUMN: `.mod_kanbanlearning_move_card_column`,
    MOVEMODALTRIGGER: `[data-action="move_card"]`,
    PUSHCARD: `[data-action="push_card"]`,
    SAVEBOARDTEMPLATE: `[data-action="save_board_as_template"]`,
    APPLYTEMPLATETOBOARD: `[data-action="apply_template_to_board"]`,
    APPLYTEMPLATETOALLGROUPBOARDS: `[data-action="apply_template_to_all_group_boards"]`,
    SCROLLLEFT: `.mod_kanbanlearning_scroll_left button`,
    SCROLLRIGHT: `.mod_kanbanlearning_scroll_right button`,
    SHOWBOARD: `[data-action="show_board"]`,
    SHOWHIDDEN: `[data-action="show_hidden"]`,
    UNASSIGNSELF: `[data-action="unassign_self"]`,
    UNASSIGNUSER: `[data-action="unassign_user"]`,
    UNCOMPLETE: `[data-action="uncomplete_card"]`,
    UNLOCKCOLUMN: `[data-action="unlock_column"]`,
    UNLOCKBOARDCOLUMNS: `[data-action="unlock_board_columns"]`,
    WIPLIMIT: `.mod_kanbanlearning_wiplimit`,
};
