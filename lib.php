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
 * Library for mod_kanbanlearning
 *
 * @package     mod_kanbanlearning
 * @copyright   2023-2024 ISB Bayern
 * @author      Stefan Hanauska <stefan.hanauska@csg-in.de>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_kanbanlearning\boardmanager;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');
require_once('HTML/QuickForm/input.php');

MoodleQuickForm::registerElementType(
    'color',
    $CFG->dirroot . '/mod/kanbanlearning/classes/form/color.php',
    'MoodleQuickForm_color'
);

/**
 * Adds a new kanbanlearning instance
 *
 * @param stdClass $data kanbanlearning record
 * @return int
 */
function kanbanlearning_add_instance($data): int {
    global $DB;
    kanbanlearning_normalize_board_group_settings($data);
    $kanbanlearningid = $DB->insert_record("kanbanlearning", $data);
    $boardmanager = new boardmanager();
    $boardmanager->load_instance($kanbanlearningid, true);
    $boardmanager->create_board();
    return $kanbanlearningid;
}

/**
 * Updates a kanbanlearning instance
 *
 * @param stdClass $data kanbanlearning record
 * @return int
 */
function kanbanlearning_update_instance($data): int {
    global $DB;
    kanbanlearning_normalize_board_group_settings($data);
    $data->id = $data->instance;
    return $DB->update_record("kanbanlearning", $data);
}

/**
 * Normalize selected board group settings before persisting the activity.
 *
 * The form sends values through both a hidden CSV field (boardgroups) and
 * the selected list (selectedboardgroups[]). This function ensures consistent
 * persistence regardless of which transport path was updated.
 *
 * @param stdClass $data Form payload.
 * @return void
 */
function kanbanlearning_normalize_board_group_settings(stdClass &$data): void {
    $data->linknumbers = empty($data->usenumbers) ? 0 : 1;

    $selectedgroupids = [];

    // First preference: raw POST from the right-side selector.
    // These custom HTML fields are not guaranteed to be present in moodleform data object.
    if (isset($_POST['selectedboardgroups'])) {
        $rawselected = $_POST['selectedboardgroups'];
        if (!is_array($rawselected)) {
            $rawselected = [$rawselected];
        }
        $selectedgroupids = array_filter(array_map('intval', $rawselected), function (int $groupid): bool {
            return $groupid > 0;
        });
    } else if (isset($data->selectedboardgroups)) {
        // Fallback when field is present in the parsed form data.
        $rawselected = $data->selectedboardgroups;
        if (!is_array($rawselected)) {
            $rawselected = [$rawselected];
        }
        $selectedgroupids = array_filter(array_map('intval', $rawselected), function (int $groupid): bool {
            return $groupid > 0;
        });
    }

    if (!empty($selectedgroupids)) {
        $selectedgroupids = array_values(array_unique($selectedgroupids));
        $data->boardgroups = implode(',', $selectedgroupids);
        $data->boardgroupid = (int)reset($selectedgroupids);
        return;
    }

    $serialized = trim((string)($data->boardgroups ?? ''));
    if ($serialized !== '') {
        $groupids = preg_split('/[;,]/', $serialized, -1, PREG_SPLIT_NO_EMPTY);
        $groupids = array_filter(array_map('intval', $groupids), function (int $groupid): bool {
            return $groupid > 0;
        });
        if (!empty($groupids)) {
            $groupids = array_values(array_unique($groupids));
            $data->boardgroups = implode(',', $groupids);
            $data->boardgroupid = (int)reset($groupids);
            return;
        }
    }

    $data->boardgroups = '';
    $data->boardgroupid = 0;
}

/**
 * Deletes a kanbanlearning instance, all boards and all associated data (e.g. files)
 *
 * @param integer $id kanbanlearning record
 * @return bool
 */
function kanbanlearning_delete_instance($id): bool {
    global $DB;
    $boards = $DB->get_fieldset_sql('SELECT id FROM {kanbanlearning_board} WHERE kanbanlearning_instance = :id', ['id' => $id]);

    foreach ($boards as $board) {
        $boardmanager = new boardmanager();
        $boardmanager->load_board($board);
        $boardmanager->delete_board($board);
    }

    return $DB->delete_records('kanbanlearning', ['id' => $id]);
}

/**
 * Returns whether a feature is supported by this module.
 *
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, false if not, null if doesn't know or string for the module purpose.
 * @uses FEATURE_GROUPINGS
 * @uses FEATURE_MOD_INTRO
 * @uses FEATURE_COMPLETION_TRACKS_VIEWS
 * @uses FEATURE_GRADE_HAS_GRADE
 * @uses FEATURE_GRADE_OUTCOMES
 * @uses FEATURE_IDNUMBER
 * @uses FEATURE_GROUPS
 */
function kanbanlearning_supports($feature) {
    switch ($feature) {
        case FEATURE_IDNUMBER:
            return true;
        case FEATURE_GROUPS:
            return true;
        case FEATURE_GROUPINGS:
            return true;
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_COMPLETION_HAS_RULES:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return false;
        case FEATURE_GRADE_OUTCOMES:
            return false;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_COLLABORATION;
        default:
            return null;
    }
}

/**
 * Implements callback inplace_editable() allowing to edit values in-place
 *
 * @param string $itemtype
 * @param int $itemid
 * @param mixed $newvalue
 * @return \core\output\inplace_editable | null
 * @throws dml_exception
 */
function kanbanlearning_inplace_editable($itemtype, $itemid, $newvalue) {
    global $CFG, $USER;
    require_once($CFG->libdir . '/externallib.php');
    $boardmanager = new boardmanager();

    if ($itemtype == 'card') {
        $card = $boardmanager->get_card($itemid);
        $boardmanager->load_board($card->kanbanlearning_board);
    }
    if ($itemtype == 'column') {
        $column = $boardmanager->get_column($itemid);
        $boardmanager->load_board($column->kanbanlearning_board);
    }

    $context = context_module::instance($boardmanager->get_cminfo()->id);
    external_api::validate_context($context);

    if ($itemtype == 'card') {
        if (!$boardmanager->can_user_manage_specific_card($card->id)) {
            throw new moodle_exception('editing_this_card_is_not_allowed', 'mod_kanbanlearning');
        }
    }

    if ($itemtype == 'column') {
        require_capability('mod/kanbanlearning:managecolumns', $context);
    }

    \mod_kanbanlearning\helper::check_permissions_for_user_or_group(
        $boardmanager->get_board(),
        $context,
        $boardmanager->get_cminfo()
    );

    if ($itemtype == 'card') {
        $boardmanager->update_card($itemid, ['title' => $newvalue]);
    }

    if ($itemtype == 'column') {
        $boardmanager->update_column($itemid, ['title' => $newvalue]);
    }

    // Return the persisted value (not the raw input) so UI updates immediately and consistently.
    $persistedvalue = (string) $newvalue;
    if ($itemtype == 'card') {
        $updatedcard = $boardmanager->get_card($itemid);
        $persistedvalue = html_entity_decode((string) $updatedcard->title, ENT_COMPAT, 'UTF-8');
    }
    if ($itemtype == 'column') {
        $updatedcolumn = $boardmanager->get_column($itemid);
        $persistedvalue = html_entity_decode((string) $updatedcolumn->title, ENT_COMPAT, 'UTF-8');
    }

    return new \core\output\inplace_editable(
        'mod_kanbanlearning',
        $itemtype,
        $itemid,
        true,
        s($persistedvalue),
        $persistedvalue,
        null,
        ''
    );
}

/**
 * Delivers the attachment files for cards
 *
 * @param stdClass $course course object
 * @param stdClass $cm course module object
 * @param stdClass $context context object
 * @param string $filearea file area
 * @param array $args extra arguments
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 * @return bool false if file not found, does not return if found - justsend the file
 */
function kanbanlearning_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []): ?bool {
    global $DB;
    require_course_login($course, true, $cm);

    // In $args[0] is the card id.

    $cardid = intval($args[0]);
    $boardid = $DB->get_field('kanbanlearning_card', 'kanbanlearning_board', ['id' => $cardid], MUST_EXIST);

    // Check, whether the user is allowed to access this board.

    require_capability('mod/kanbanlearning:view', $context);

    $board = mod_kanbanlearning\helper::get_cached_board($boardid);

    mod_kanbanlearning\helper::check_permissions_for_user_or_group($board, $context, cm_info::create($cm));

    $fullpath = "/$context->id/mod_kanbanlearning/$filearea/" . implode('/', $args);

    $fs = get_file_storage();
    if (!($file = $fs->get_file_by_hash(sha1($fullpath))) || $file->is_directory()) {
        return false;
    }

    send_stored_file($file, 0, 0, false, $options);
}

/**
 * Implementation of the function for printing the form elements that control
 * whether the course reset functionality affects the kanbanlearning activity.
 *
 * @param object $mform form passed by reference
 */
function kanbanlearning_reset_course_form_definition(&$mform): void {
    $mform->addElement('header', 'kanbanlearningactivityheader', get_string('modulenameplural', 'mod_kanbanlearning'));
    $mform->addElement('advcheckbox', 'reset_kanbanlearning_personal', get_string('reset_personal', 'mod_kanbanlearning'));
    $mform->addElement('advcheckbox', 'reset_kanbanlearning_group', get_string('reset_group', 'mod_kanbanlearning'));
    $mform->addElement('advcheckbox', 'reset_kanbanlearning', get_string('reset_kanbanlearning', 'mod_kanbanlearning'));
}

/**
 * Course reset form defaults.
 *
 * @param stdClass $course the course object
 * @return array
 */
function kanbanlearning_reset_course_form_defaults(stdClass $course): array {
    return [
        'reset_kanbanlearning_personal' => 1,
        'reset_kanbanlearning_group' => 1,
        'reset_kanbanlearning' => 1,
    ];
}

/**
 * This function is used by the reset_course_userdata function in moodlelib.
 *
 * @param object $data the data submitted from the reset course.
 * @return array status array
 */
function kanbanlearning_reset_userdata($data) {
    global $DB;
    $status = [];
    $kanbanlearnings = $DB->get_records('kanbanlearning', ['course' => $data->courseid]);
    $boards = [];
    foreach ($kanbanlearnings as $kanbanlearning) {
        if (!empty($data->reset_kanbanlearning_personal)) {
            $personalboards = $DB->get_fieldset_sql(
                'SELECT id FROM {kanbanlearning_board} WHERE kanbanlearning_instance = :id AND userid > 0',
                ['id' => $kanbanlearning->id]
            );
            if ($personalboards) {
                $boards = array_merge($boards, $personalboards);
            }
        }
        if (!empty($data->reset_kanbanlearning_group)) {
            $groupboards = $DB->get_fieldset_sql(
                'SELECT id FROM {kanbanlearning_board} WHERE kanbanlearning_instance = :id AND groupid > 0',
                ['id' => $kanbanlearning->id]
            );
            if ($groupboards) {
                $boards = array_merge($boards, $groupboards);
            }
        }
        if (!empty($data->reset_kanbanlearning)) {
            $courseboards = $DB->get_fieldset_sql(
                'SELECT id FROM {kanbanlearning_board} WHERE kanbanlearning_instance = :id AND template = 0',
                ['id' => $kanbanlearning->id]
            );
            if ($courseboards) {
                $boards = array_merge($boards, $courseboards);
            }
        }
    }
    $boards = array_unique($boards);
    foreach ($boards as $board) {
        $boardmanager = new boardmanager();
        $boardmanager->load_board($board);
        $boardmanager->delete_board($board);
        $status[] = [
            'component' => get_string('modulenameplural', 'mod_kanbanlearning'),
            'item' => get_string('reset_personal', 'mod_kanbanlearning'),
            'error' => false,
        ];
    }
    return $status;
}

/**
 * Add custom completion.
 *
 * @param stdClass $cm coursemodule record.
 * @return cached_cm_info
 */
function kanbanlearning_get_coursemodule_info(stdClass $cm): cached_cm_info {
    global $DB;

    $kanbanlearning = $DB->get_record('kanbanlearning', ['id' => $cm->instance]);

    $result = new cached_cm_info();
    if ($kanbanlearning) {
        $result->name = $kanbanlearning->name;

        if ($cm->showdescription) {
            $result->content = format_module_intro('kanbanlearning', $kanbanlearning, $cm->id, false);
        }

        if ($cm->completion == COMPLETION_TRACKING_AUTOMATIC) {
            $result->customdata['customcompletionrules']['completioncreate'] = $kanbanlearning->completioncreate;
            $result->customdata['customcompletionrules']['completioncomplete'] = $kanbanlearning->completioncomplete;
        }
    }
    return $result;
}
