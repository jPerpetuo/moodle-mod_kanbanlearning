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
                    'const frame = document.getElementById(' . $frameidjson . ');' .
                    'return frame && frame.contentDocument && frame.contentDocument.body ?' .
                    ' frame.contentDocument.body.innerText : "";'
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
}
