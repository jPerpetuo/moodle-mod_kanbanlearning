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

    /**
     * Focus the reaction control on the completed card so keyboard interaction can be tested.
     *
     * @When /^I focus the approval reaction control on the completed Kanban card$/
     */
    public function i_focus_approval_reaction_control_on_completed_card(): void {
        if (!$this->running_javascript()) {
            throw new \Behat\Mink\Exception\DriverException(
                'Focusing the approval reaction control requires JavaScript.',
                $this->getSession()
            );
        }

        $selector = '.mod_kanbanlearning_column_container > .mod_kanbanlearning_column:last-child ' .
            '.mod_kanbanlearning_card [data-action="toggle_approval_seal"]';
        $selectorjson = json_encode($selector, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
        $this->execute_script(
            'const control = document.querySelector(' . $selectorjson . ');' .
            'if (!control || control.getClientRects().length === 0) {' .
            'throw new Error("The visible approval reaction control was not found.");' .
            '}' .
            'control.focus();'
        );
    }

    /**
     * Drag a card to the bottom of a target column using the board's native HTML5 drag handlers.
     *
     * @When /^I drag the "([^"]*)" card to the bottom of the "([^"]*)" column on the Kanban board$/
     * @param string $cardtitle Visible title of the card.
     * @param string $targettitle Visible title of the destination column.
     */
    public function i_drag_card_to_bottom_of_column_on_kanban_board(string $cardtitle, string $targettitle): void {
        if (!$this->running_javascript()) {
            throw new \\Behat\\Mink\\Exception\\DriverException(
                'Kanban card drag and drop requires JavaScript.',
                $this->getSession()
            );
        }

        $cardtitlejson = json_encode($cardtitle, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
        $targettitlejson = json_encode($targettitle, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
        $script = 'const cardtitle = ' . $cardtitlejson . ";\\n" .
            'const targettitle = ' . $targettitlejson . ";\\n" .
            "const cards = Array.from(document.querySelectorAll('.mod_kanbanlearning_card'));\\n" .
            "const source = cards.find((card) => {\\n" .
            "    const link = card.querySelector('.mod_kanbanlearning_card_title a.quickeditlink');\\n" .
            "    return link && link.textContent.trim() === cardtitle;\\n" .
            "});\\n" .
            "const columns = Array.from(document.querySelectorAll('.mod_kanbanlearning_column'));\\n" .
            "const target = columns.find((column) => {\\n" .
            "    const link = column.querySelector('a.quickeditlink');\\n" .
            "    return link && link.textContent.trim() === targettitle;\\n" .
            "});\\n" .
            "if (!source || !target || source.closest('.mod_kanbanlearning_column') === target) {\\n" .
            "    throw new Error('Kanban card or destination column for the drag step was not found.');\\n" .
            "}\\n" .
            "const bounds = target.getBoundingClientRect();\\n" .
            "const clientY = Math.max(bounds.top + 1, bounds.bottom - 1);\\n" .
            "const dataTransfer = new DataTransfer();\\n" .
            "const dragoptions = {bubbles: true, cancelable: true, dataTransfer: dataTransfer, clientY: clientY};\\n" .
            "source.dispatchEvent(new DragEvent('dragstart', dragoptions));\\n" .
            "target.dispatchEvent(new DragEvent('dragenter', dragoptions));\\n" .
            "target.dispatchEvent(new DragEvent('dragover', dragoptions));\\n" .
            "target.dispatchEvent(new DragEvent('drop', dragoptions));\\n" .
            "source.dispatchEvent(new DragEvent('dragend', dragoptions));";
        $this->execute_script($script);
    }

    /**
     * Verify that an authenticated student cannot download an attachment from another group's board.
     *
     * @Then /^a student outside group "([^"]*)" cannot download a card attachment$/
     * @param string $groupidnumber Group idnumber whose board belongs to another group.
     */
    public function student_outside_group_cannot_download_card_attachment(string $groupidnumber): void {
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
        if (empty($columnids[0])) {
            throw new coding_exception('The group board needs a column for the attachment access test.');
        }

        $boardmanager = new \\mod_kanbanlearning\\boardmanager($cmid, (int)$board->id);
        $cardid = $boardmanager->add_card($columnids[0], 0, ['title' => 'Private group attachment test']);
        $context = context_module::instance($cmid);
        $filename = 'private-group-attachment.txt';
        $secret = 'group-private-file-' . bin2hex(random_bytes(8));
        get_file_storage()->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'mod_kanbanlearning',
            'filearea' => 'attachments',
            'itemid' => $cardid,
            'filepath' => '/',
            'filename' => $filename,
        ], $secret);

        $fileurl = moodle_url::make_pluginfile_url(
            $context->id,
            'mod_kanbanlearning',
            'attachments',
            $cardid,
            '/',
            $filename
        )->out(false);
        $fileurljson = json_encode($fileurl, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
        $secretjson = json_encode($secret, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
        $nodeid = 'kanbanlearning-group-attachment-check';
        $nodeidjson = json_encode($nodeid);

        $this->execute_script(
            '(function() {' .
            'var result = document.createElement("pre");' .
            'result.id = ' . $nodeidjson . ';' .
            'result.style.display = "none";' .
            'document.body.appendChild(result);' .
            'fetch(' . $fileurljson . ', {credentials: "same-origin", cache: "no-store"})' .
            '.then(async function(response) {' .
            'result.textContent = JSON.stringify({status: response.status, body: await response.text()});' .
            'result.setAttribute("data-complete", "1");' .
            '}).catch(function(error) {' .
            'result.textContent = JSON.stringify({error: String(error)});' .
            'result.setAttribute("data-complete", "1");' .
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

        $outcome = is_string($response) ? json_decode($response, true) : null;
        if (!is_array($outcome) || empty($outcome['status'])) {
            throw new \\Behat\\Mink\\Exception\\ExpectationException(
                'Could not verify the response for the cross-group attachment request.',
                $this->getSession()->getDriver()
            );
        }
        if (strpos((string)($outcome['body'] ?? ''), $secret) !== false) {
            throw new \\Behat\\Mink\\Exception\\ExpectationException(
                'A student from another group received the private card attachment.',
                $this->getSession()->getDriver()
            );
        }
    }


    /**
     * Open the completed-card reaction picker with a simulated reduced-motion preference.
     *
     * @When /^I open the approval reaction picker with reduced motion enabled$/
     */
    public function i_open_approval_reaction_picker_with_reduced_motion_enabled(): void {
        if (!$this->running_javascript()) {
            throw new \Behat\Mink\Exception\DriverException(
                'The reduced-motion reaction test requires JavaScript.',
                $this->getSession()
            );
        }

        $selector = '.mod_kanbanlearning_column_container > .mod_kanbanlearning_column:last-child ' .
            '.mod_kanbanlearning_card [data-action="toggle_approval_seal"]';
        $selectorjson = json_encode($selector, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
        $this->execute_script(
            '(function() {' .
            'var control = document.querySelector(' . $selectorjson . ');' .
            'var card = control && control.closest(".mod_kanbanlearning_card");' .
            'var picker = card && card.querySelector(".mod_kanbanlearning_approval_seal_picker");' .
            'if (!control || !picker || !control.getClientRects().length) {' .
            'throw new Error("The visible completed-card reaction control was not found.");' .
            '}' .
            'var originalMatchMedia = window.matchMedia.bind(window);' .
            'window.matchMedia = function(query) {' .
            'if (query === "(prefers-reduced-motion: reduce)") {' .
            'return {matches: true, media: query, onchange: null, addListener: function() {}, ' .
            'removeListener: function() {}, addEventListener: function() {}, removeEventListener: function() {}, ' .
            'dispatchEvent: function() { return false; }};' .
            '}' .
            'return originalMatchMedia(query);' .
            '};' .
            'try {' .
            'control.click();' .
            'var animation = picker.__modKanbanApprovalSealAnimation;' .
            'var properties = animation ? animation.effect.getKeyframes().flatMap(function(frame) {' .
            'return Object.keys(frame).filter(function(key) {' .
            'return ["offset", "easing", "composite", "computedOffset"].indexOf(key) === -1;' .
            '});' .
            '}) : [];' .
            'picker.dataset.reducedMotionTest = JSON.stringify({' .
            'reducedClass: picker.classList.contains("mod_kanbanlearning_approval_seal_picker_reduced_motion"),' .
            'ariaHidden: picker.getAttribute("aria-hidden"),' .
            'ariaExpanded: control.getAttribute("aria-expanded"),' .
            'duration: animation ? animation.effect.getTiming().duration : null,' .
            'properties: properties' .
            '});' .
            '} finally {' .
            'window.matchMedia = originalMatchMedia;' .
            '}' .
            '})()'
        );
    }

    /**
     * Assert reduced motion keeps the picker accessible and animates opacity only.
     *
     * @Then /^the approval reaction picker uses opacity-only reduced motion$/
     */
    public function approval_reaction_picker_uses_opacity_only_reduced_motion(): void {
        $selector = '.mod_kanbanlearning_column_container > .mod_kanbanlearning_column:last-child ' .
            '.mod_kanbanlearning_card .mod_kanbanlearning_approval_seal_picker';
        $selectorjson = json_encode($selector, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
        $result = $this->getSession()->evaluateScript(
            '(function() {' .
            'var picker = document.querySelector(' . $selectorjson . ');' .
            'return picker ? picker.dataset.reducedMotionTest || "" : "";' .
            '})()'
        );
        $data = is_string($result) ? json_decode($result, true) : null;

        if (
            !is_array($data) ||
            empty($data['reducedClass']) ||
            ($data['ariaHidden'] ?? '') !== 'false' ||
            ($data['ariaExpanded'] ?? '') !== 'true' ||
            ($data['duration'] ?? null) !== 100 ||
            empty($data['properties']) ||
            array_diff($data['properties'], ['opacity'])
        ) {
            throw new \Behat\Mink\Exception\ExpectationException(
                'The reaction picker must stay accessible and animate only opacity for reduced motion.',
                $this->getSession()->getDriver()
            );
        }
    }



}
