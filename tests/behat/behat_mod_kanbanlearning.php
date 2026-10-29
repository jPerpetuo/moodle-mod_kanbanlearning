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
 * Behat steps for the Kanban for Learning activity.
 *
 * @package    mod_kanbanlearning
 * @category   test
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// This file may be loaded by Behat before config.php is included.
$behatbase = __DIR__ . '/../../../../lib/behat/behat_base.php';
if (!file_exists($behatbase)) {
    $behatbase = __DIR__ . '/../../../../../lib/behat/behat_base.php';
}
require_once($behatbase);

/**
 * Behat steps definitions for mod_kanbanlearning.
 */
class behat_mod_kanbanlearning extends behat_base {
    /**
     * Drag one column after another using the browser drag events handled by Moodle's reactive components.
     *
     * The generic Mink drag step does not reliably trigger the HTML5 drag events used by the board.
     *
     * @When /^I drag the "([^"]*)" column after the "([^"]*)" column on the Kanban board$/
     * @param string $sourcetitle Title of the column to move.
     * @param string $targettitle Title of the column that should precede it.
     */
    public function i_drag_column_after_column_on_kanban_board(string $sourcetitle, string $targettitle): void {
        if (!$this->running_javascript()) {
            throw new \Behat\Mink\Exception\DriverException(
                'Kanban column reordering requires JavaScript.',
                $this->getSession()
            );
        }

        $sourcetitlejson = json_encode($sourcetitle, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
        $targettitlejson = json_encode($targettitle, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
        $script = 'const sourcetitle = ' . $sourcetitlejson . ";\n" .
            'const targettitle = ' . $targettitlejson . ";\n" .
            "const columns = Array.from(document.querySelectorAll('.mod_kanbanlearning_column'));\n" .
            "const findcolumn = (title) => columns.find((column) => {\n" .
            "    const link = column.querySelector('a.quickeditlink');\n" .
            "    return link && link.textContent.trim() === title;\n" .
            "});\n" .
            "const source = findcolumn(sourcetitle);\n" .
            "const target = findcolumn(targettitle);\n" .
            "if (!source || !target || source === target) {\n" .
            "    throw new Error('Kanban columns for the drag step were not found.');\n" .
            "}\n" .
            "const dataTransfer = new DataTransfer();\n" .
            "const dragoptions = {bubbles: true, cancelable: true, dataTransfer: dataTransfer};\n" .
            "source.dispatchEvent(new DragEvent('dragstart', dragoptions));\n" .
            "target.dispatchEvent(new DragEvent('dragenter', dragoptions));\n" .
            "target.dispatchEvent(new DragEvent('dragover', dragoptions));\n" .
            "target.dispatchEvent(new DragEvent('drop', dragoptions));\n" .
            "source.dispatchEvent(new DragEvent('dragend', dragoptions));";
        $this->execute_script($script);
    }

    /**
     * Verify that directly requesting another group's board is denied.
     *
     * The request is made in a hidden iframe so Moodle's Behat error handler does not treat the expected
     * error page as a failure of the browser navigation step.
     *
     * @Then /^the direct URL for group "([^"]*)" should be denied$/
     * @param string $groupidnumber Group idnumber in the current course.
     */
    public function direct_kanban_group_board_url_should_be_denied(string $groupidnumber): void {
        global $DB;

        $currenturl = new moodle_url($this->getSession()->getCurrentUrl());
        $cmid = (int)$currenturl->get_param('id');
        if (empty($cmid)) {
            throw new coding_exception('The current page is not a Kanban activity page.');
        }

        $cm = get_coursemodule_from_id('kanbanlearning', $cmid, 0, false, MUST_EXIST);
        $group = $DB->get_record('groups', [
            'courseid' => $cm->course,
            'idnumber' => $groupidnumber,
        ], '*', MUST_EXIST);
        $board = $DB->get_record('kanbanlearning_board', [
            'kanbanlearning_instance' => $cm->instance,
            'groupid' => $group->id,
            'userid' => 0,
            'template' => 0,
        ], '*', MUST_EXIST);

        $url = new moodle_url('/mod/kanbanlearning/view.php', [
            'id' => $cmid,
            'boardid' => $board->id,
        ]);
        $urljson = json_encode($url->out(false), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
        $frameid = 'kanbanlearning-group-access-check';
        $frameidjson = json_encode($frameid);
        $this->execute_script(
            'const frame = document.createElement("iframe");' .
            'frame.id = ' . $frameidjson . ';' .
            'frame.setAttribute("aria-hidden", "true");' .
            'frame.style.display = "none";' .
            'frame.src = ' . $urljson . ';' .
            'document.body.appendChild(frame);'
        );

        $expectedmessage = get_string('boardgroupaccessdenied', 'mod_kanbanlearning');
        $deadline = microtime(true) + 10;
        $response = '';
        try {
            do {
                $response = $this->getSession()->evaluateScript(
                    '(function() {' .
                    'var frame = document.getElementById(' . $frameidjson . ');' .
                    'return frame && frame.contentDocument && frame.contentDocument.body ?' .
                    ' frame.contentDocument.body.innerText : "";' .
                    '})()'
                );
                if (is_string($response) && strpos($response, $expectedmessage) !== false) {
                    return;
                }
                usleep(100000);
            } while (microtime(true) < $deadline);

            throw new \Behat\Mink\Exception\ExpectationException(
                'Expected the direct group board URL to show the access-denied message.',
                $this->getSession()->getDriver()
            );
        } finally {
            $this->execute_script(
                'const frame = document.getElementById(' . $frameidjson . ');' .
                'if (frame) { frame.remove(); }'
            );
        }
    }

    /**
     * Verify that a student assigned to a card in another group's board cannot modify that card or its columns.
     *
     * The calls use Moodle's AJAX web-service client from the student's authenticated browser session. The
     * fixture card is assigned to the current test student so the card-level capability check passes and the
     * group-membership check is what rejects the move.
     *
     * @Then /^a student outside group "([^"]*)" cannot move an assigned card or add a column$/
     * @param string $groupidnumber Group idnumber whose board belongs to another group.
     */
    public function student_outside_group_cannot_modify_group_board(string $groupidnumber): void {
        global $DB;

        $currenturl = new moodle_url($this->getSession()->getCurrentUrl());
        $cmid = (int)$currenturl->get_param('id');
        if (empty($cmid)) {
            throw new coding_exception('The current page is not a Kanban activity page.');
        }

        $cm = get_coursemodule_from_id('kanbanlearning', $cmid, 0, false, MUST_EXIST);
        $group = $DB->get_record('groups', [
            'courseid' => $cm->course,
            'idnumber' => $groupidnumber,
        ], '*', MUST_EXIST);
        $board = $DB->get_record('kanbanlearning_board', [
            'kanbanlearning_instance' => $cm->instance,
            'groupid' => $group->id,
            'userid' => 0,
            'template' => 0,
        ], '*', MUST_EXIST);

        $columnids = array_map('intval', explode(',', $board->sequence));
        if (count($columnids) < 2) {
            throw new coding_exception('The group board needs at least two columns for the access test.');
        }

        // Create a card and assign it to the current Behat student so that move permissions are otherwise valid.
        $boardmanager = new \mod_kanbanlearning\boardmanager($cmid, (int)$board->id);
        $sourcecolumnid = $columnids[0];
        $targetcolumnid = $columnids[1];
        $cardtitle = 'Group access regression ' . bin2hex(random_bytes(6));
        $cardid = $boardmanager->add_card($sourcecolumnid, 0, ['title' => $cardtitle]);
        $student = $DB->get_record('user', ['username' => 'student1'], '*', MUST_EXIST);
        $DB->insert_record('kanbanlearning_assignee', [
            'kanbanlearning_card' => $cardid,
            'userid' => $student->id,
        ]);

        $beforecolumncount = $DB->count_records('kanbanlearning_column', ['kanbanlearning_board' => $board->id]);
        $requests = [
            [
                'methodname' => 'mod_kanbanlearning_move_card',
                'args' => [
                    'cmid' => $cmid,
                    'boardid' => (int)$board->id,
                    'data' => [
                        'cardid' => $cardid,
                        'columnid' => $targetcolumnid,
                        'aftercard' => 0,
                    ],
                ],
            ],
            [
                'methodname' => 'mod_kanbanlearning_add_column',
                'args' => [
                    'cmid' => $cmid,
                    'boardid' => (int)$board->id,
                    'data' => [
                        'title' => 'Unauthorized group column',
                        'aftercol' => 0,
                    ],
                ],
            ],
        ];
        $requestsjson = json_encode($requests, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
        $nodeid = 'kanbanlearning-group-operation-check';
        $nodeidjson = json_encode($nodeid);

        $this->execute_script(
            '(function() {' .
            'var result = document.createElement("pre");' .
            'result.id = ' . $nodeidjson . ';' .
            'result.style.display = "none";' .
            'document.body.appendChild(result);' .
            'var requests = ' . $requestsjson . ';' .
            'require(["core/ajax"], function(Ajax) {' .
            'var attempts = requests.map(function(request) {' .
            'return Ajax.call([request])[0].then(' .
            'function() { return {status: "allowed"}; },' .
            'function(error) { return {status: "denied", message: error && error.message ? error.message : ""}; }' .
            ');' .
            '});' .
            'Promise.all(attempts).then(function(outcomes) {' .
            'result.textContent = JSON.stringify(outcomes);' .
            'result.setAttribute("data-complete", "1");' .
            '}).catch(function(error) {' .
            'result.textContent = JSON.stringify([{status: "client-error", message: String(error)}]);' .
            'result.setAttribute("data-complete", "1");' .
            '});' .
            '});' .
            '})()'
        );

        $deadline = microtime(true) + 10;
        $response = '';
        try {
            do {
                $response = $this->getSession()->evaluateScript(
                    '(function() {' .
                    'var node = document.getElementById(' . $nodeidjson . ');' .
                    'return node && node.getAttribute("data-complete") === "1" ? node.textContent : "";' .
                    '})()'
                );
                if (is_string($response) && $response !== '') {
                    break;
                }
                usleep(100000);
            } while (microtime(true) < $deadline);
        } finally {
            $this->execute_script(
                'var node = document.getElementById(' . $nodeidjson . '); if (node) { node.remove(); }'
            );
        }

        $outcomes = is_string($response) ? json_decode($response, true) : null;
        if (
            !is_array($outcomes) ||
            count($outcomes) !== 2 ||
            ($outcomes[0]['status'] ?? '') !== 'denied' ||
            ($outcomes[1]['status'] ?? '') !== 'denied'
        ) {
            throw new \Behat\Mink\Exception\ExpectationException(
                'A student from another group must not move the assigned card or add a column.',
                $this->getSession()->getDriver()
            );
        }

        $card = $DB->get_record('kanbanlearning_card', ['id' => $cardid], '*', MUST_EXIST);
        if (
            (int)$card->kanbanlearning_column !== $sourcecolumnid ||
            $DB->count_records('kanbanlearning_column', ['kanbanlearning_board' => $board->id]) !== $beforecolumncount
        ) {
            throw new \Behat\Mink\Exception\ExpectationException(
                'A denied group operation changed the board data.',
                $this->getSession()->getDriver()
            );
        }
    }
}
