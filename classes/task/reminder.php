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
 * Reminder task
 *
 * @package    mod_kanbanlearning
 * @copyright   2023-2024 ISB Bayern
 * @author     Stefan Hanauska
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_kanbanlearning\task;

use mod_kanbanlearning\helper;

/**
 * Reminder task
 *
 * @package    mod_kanbanlearning
 * @copyright   2023-2024 ISB Bayern
 * @author     Stefan Hanauska
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reminder extends \core\task\scheduled_task {
    /**
     * Return the task's name as shown in admin screens.
     *
     * @return string
     */
    public function get_name() {
        return get_string('remindertask', 'mod_kanbanlearning');
    }

    /**
     * Execute the task.
     */
    public function execute() {
        global $DB;
        $time = time();
        $kanbanlearningcards = $DB->get_records_sql(
            'SELECT ' . $DB->sql_concat('c.id', "'-'", 'a.userid') . ' as uniqid,
                    c.id as id, c.title as title, k.name as boardname, c.duedate as duedate, a.userid as userid, k.id as instance
               FROM {kanbanlearning_card} c
         INNER JOIN {kanbanlearning_assignee} a ON a.kanbanlearning_card = c.id
                AND c.duedate != 0
                AND c.reminder_sent = 0
                AND c.completed = 0
                AND (c.duedate < :time OR (c.reminderdate != 0 AND c.reminderdate < :time2))
         INNER JOIN {kanbanlearning_board} b ON b.id = c.kanbanlearning_board
         INNER JOIN {kanbanlearning} k ON b.kanbanlearning_instance = k.id',
            ['time' => $time, 'time2' => $time]
        );
        foreach ($kanbanlearningcards as $kanbanlearningcard) {
            [$course, $cminfo] = get_course_and_cm_from_instance($kanbanlearningcard->instance, 'kanbanlearning');
            $user = \core_user::get_user($kanbanlearningcard->userid);
            helper::fix_current_language($user->lang);
            $kanbanlearningcard->duedate = userdate($kanbanlearningcard->duedate, get_string('strftimedate', 'langconfig'));
            helper::send_notification($cminfo, 'due', [$kanbanlearningcard->userid], $kanbanlearningcard, null, true);
            $data = new \stdClass();
            $data->id = $kanbanlearningcard->id;
            $data->reminder_sent = 1;
            $DB->update_record('kanbanlearning_card', $data);
        }
    }
}
