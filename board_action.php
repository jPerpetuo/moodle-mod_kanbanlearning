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

/**
 * Execute board-level kanbanlearning actions that are easier to handle via redirect than via reactive updates.
 *
 * @package     mod_kanbanlearning
 * @copyright   2026 CCEAD PUC-Rio
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->dirroot . '/mod/kanbanlearning/lib.php');

use mod_kanbanlearning\boardmanager;
use mod_kanbanlearning\constants;
use mod_kanbanlearning\helper;

$id = required_param('id', PARAM_INT);
$boardid = required_param('boardid', PARAM_INT);
$action = required_param('action', PARAM_ALPHAEXT);
$confirmoverwrite = optional_param('confirmoverwrite', 0, PARAM_BOOL);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'kanbanlearning');
require_course_login($course, true, $cm);
require_sesskey();

$context = context_module::instance($cm->id);
require_capability('mod/kanbanlearning:manageboard', $context);

$boardmanager = new boardmanager($cm->id, $boardid);
$board = $boardmanager->get_board();
$kanbanlearning = $DB->get_record('kanbanlearning', ['id' => $cm->instance], '*', MUST_EXIST);
$boardmode = (int)($kanbanlearning->boardmode ?? constants::MOD_KANBANLEARNING_BOARDMODE_SHARED);

helper::check_permissions_for_user_or_group($board, $context, $cm);

if ($boardmode !== constants::MOD_KANBANLEARNING_BOARDMODE_GROUP) {
    throw new moodle_exception('templateactionsrequiregroupmode', 'mod_kanbanlearning');
}

$redirecturl = new moodle_url('/mod/kanbanlearning/view.php', [
    'id' => $cm->id,
    'boardid' => $boardid,
]);

switch ($action) {
    case 'save_template':
        $boardmanager->create_template();
        redirect($redirecturl, get_string('templatesaved', 'mod_kanbanlearning'), null, \core\output\notification::NOTIFY_SUCCESS);
        break;
    case 'apply_template_to_board':
        $boardmanager->apply_template_to_board($boardid, 0, (bool)$confirmoverwrite);
        redirect(
            $redirecturl,
            get_string('templateappliedtoboard', 'mod_kanbanlearning'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
        break;
    case 'apply_template_to_all_group_boards':
        $boardmanager->apply_template_to_all_group_boards(
            0,
            (bool)$confirmoverwrite
        );
        redirect(
            $redirecturl,
            get_string('templateappliedtoallgroupboards', 'mod_kanbanlearning'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
        break;
    default:
        throw new moodle_exception('invalidaction');
}
