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

namespace mod_kanbanlearning\task;

use mod_kanbanlearning\boardmanager;

/**
 * Unit test for mod_kanbanlearning
 *
 * @package     mod_kanbanlearning
 * @copyright   2023-2024 ISB Bayern
 * @author      Stefan Hanauska <stefan.hanauska@csg-in.de>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \mod_kanbanlearning\task\reminder
 */
final class reminder_test extends \advanced_testcase {
    /** @var \stdClass The course used for testing */
    private $course;
    /** @var \stdClass The kanbanlearning used for testing */
    private $kanbanlearning;
    /** @var array The users used for testing */
    private $users;

    /**
     * Prepare testing environment.
     */
    public function setUp(): void {
        global $DB;

        parent::setUp();

        $this->course = $this->getDataGenerator()->create_course();
        $this->kanbanlearning = $this->getDataGenerator()->create_module(
            'kanbanlearning',
            ['course' => $this->course]
        );

        for ($i = 0; $i < 3; $i++) {
            $this->users[$i] = $this->getDataGenerator()->create_user([
                'email' => $i . 'user@example.com',
                'username' => 'userid' . $i,
            ]);
        }

        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        $teacherrole = $DB->get_record('role', ['shortname' => 'editingteacher']);
        $this->getDataGenerator()->enrol_user($this->users[0]->id, $this->course->id, $studentrole->id);
        $this->getDataGenerator()->enrol_user($this->users[1]->id, $this->course->id, $studentrole->id);
        $this->getDataGenerator()->enrol_user($this->users[2]->id, $this->course->id, $teacherrole->id);
    }

    /**
     * An overdue card notifies each assignee once and records that the reminder was sent.
     *
     * @return void
     */
    public function test_overdue_due_date_sends_once_to_each_assignee(): void {
        global $DB;

        $this->resetAfterTest();
        [$manager, $columnid] = $this->create_test_board();

        $unassignedid = $this->create_test_card(
            $manager,
            $columnid,
            'Overdue card without assignees',
            time() - DAYSECS
        );
        $duedate = time() - DAYSECS;
        $cardid = $this->create_test_card(
            $manager,
            $columnid,
            'Overdue assigned card',
            $duedate,
            0,
            [$this->users[0]->id, $this->users[1]->id]
        );

        $reminder = new reminder();
        $reminder->execute();

        $this->assert_due_notification(
            $this->users[0]->id,
            'Overdue assigned card',
            $manager->get_board()->name,
            $duedate
        );
        $this->assert_due_notification(
            $this->users[1]->id,
            'Overdue assigned card',
            $manager->get_board()->name,
            $duedate
        );
        $this->assertEmpty($this->get_due_notifications($this->users[2]->id));
        $this->assertEquals(1, $DB->get_field('kanbanlearning_card', 'reminder_sent', ['id' => $cardid]));
        $this->assertEquals(0, $DB->get_field('kanbanlearning_card', 'reminder_sent', ['id' => $unassignedid]));

        $reminder->execute();

        $this->assertCount(1, $this->get_due_notifications($this->users[0]->id));
        $this->assertCount(1, $this->get_due_notifications($this->users[1]->id));
        $this->assertEmpty($this->get_due_notifications($this->users[2]->id));
    }

    /**
     * A reminder date can trigger a notification before the due date.
     *
     * @return void
     */
    public function test_reminder_date_sends_before_due_date(): void {
        $this->resetAfterTest();
        [$manager, $columnid] = $this->create_test_board();

        $duedate = time() + (10 * DAYSECS);
        $cardid = $this->create_test_card(
            $manager,
            $columnid,
            'Early reminder card',
            $duedate,
            time() - HOURSECS,
            [$this->users[0]->id, $this->users[2]->id]
        );

        (new reminder())->execute();

        $this->assert_due_notification(
            $this->users[0]->id,
            'Early reminder card',
            $manager->get_board()->name,
            $duedate
        );
        $this->assert_due_notification(
            $this->users[2]->id,
            'Early reminder card',
            $manager->get_board()->name,
            $duedate
        );
        $this->assertEmpty($this->get_due_notifications($this->users[1]->id));
        $this->assertEquals(1, $this->get_card_field($cardid, 'reminder_sent'));
    }

    /**
     * Future dates and overdue cards without assignees must not trigger reminders.
     *
     * @return void
     */
    public function test_future_dates_and_unassigned_overdue_cards_do_not_send(): void {
        $this->resetAfterTest();
        [$manager, $columnid] = $this->create_test_board();

        $futurecardid = $this->create_test_card(
            $manager,
            $columnid,
            'Future card',
            time() + (10 * DAYSECS),
            time() + (5 * DAYSECS),
            [$this->users[0]->id]
        );
        $unassignedcardid = $this->create_test_card(
            $manager,
            $columnid,
            'Unassigned overdue card',
            time() - DAYSECS
        );

        (new reminder())->execute();

        foreach ($this->users as $user) {
            $this->assertEmpty($this->get_due_notifications($user->id));
        }
        $this->assertEquals(0, $this->get_card_field($futurecardid, 'reminder_sent'));
        $this->assertEquals(0, $this->get_card_field($unassignedcardid, 'reminder_sent'));
    }

    /**
     * Completed cards and cards already notified must be skipped.
     *
     * @return void
     */
    public function test_completed_and_already_notified_cards_are_skipped(): void {
        global $DB;

        $this->resetAfterTest();
        [$manager, $columnid] = $this->create_test_board();

        $completedid = $this->create_test_card(
            $manager,
            $columnid,
            'Completed overdue card',
            time() - DAYSECS,
            0,
            [$this->users[0]->id],
            true
        );
        $notifiedid = $this->create_test_card(
            $manager,
            $columnid,
            'Already notified overdue card',
            time() - DAYSECS,
            0,
            [$this->users[1]->id]
        );
        $DB->set_field('kanbanlearning_card', 'reminder_sent', 1, ['id' => $notifiedid]);

        (new reminder())->execute();

        foreach ($this->users as $user) {
            $this->assertEmpty($this->get_due_notifications($user->id));
        }
        $this->assertEquals(0, $this->get_card_field($completedid, 'reminder_sent'));
        $this->assertEquals(1, $this->get_card_field($notifiedid, 'reminder_sent'));
    }

    /**
     * Create a board and return its first column.
     *
     * @return array
     */
    private function create_test_board(): array {
        $manager = new boardmanager($this->kanbanlearning->cmid);
        $boardid = $manager->create_board();
        $manager->load_board($boardid);
        $columnids = explode(',', $manager->get_board()->sequence);

        return [$manager, (int)$columnids[0]];
    }

    /**
     * Create a test card and optionally assign users or mark it completed.
     *
     * @param boardmanager $manager Board manager.
     * @param int $columnid Column id.
     * @param string $title Card title.
     * @param int $duedate Due timestamp.
     * @param int $reminderdate Reminder timestamp.
     * @param array $assignees User ids.
     * @param bool $completed Whether the card should be completed.
     * @return int Card id.
     */
    private function create_test_card(
        boardmanager $manager,
        int $columnid,
        string $title,
        int $duedate,
        int $reminderdate = 0,
        array $assignees = [],
        bool $completed = false
    ): int {
        global $DB;

        $cardid = $manager->add_card($columnid, 0, [
            'title' => $title,
            'duedate' => $duedate,
            'reminderdate' => $reminderdate,
        ]);
        foreach ($assignees as $userid) {
            $manager->assign_user($cardid, (int)$userid);
        }
        if ($completed) {
            $DB->set_field('kanbanlearning_card', 'completed', 1, ['id' => $cardid]);
        }

        return $cardid;
    }

    /**
     * Return due notifications for one user.
     *
     * @param int $userid User id.
     * @return array
     */
    private function get_due_notifications(int $userid): array {
        global $DB;

        $noreply = \core_user::get_noreply_user();

        return $DB->get_records('notifications', [
            'useridfrom' => $noreply->id,
            'useridto' => $userid,
            'component' => 'mod_kanbanlearning',
            'eventtype' => 'due',
        ]);
    }

    /**
     * Assert that a user's due notification contains the expected card details.
     *
     * @param int $userid User id.
     * @param string $title Card title.
     * @param string $boardname Board name.
     * @param int $duedate Due timestamp.
     * @return void
     */
    private function assert_due_notification(int $userid, string $title, string $boardname, int $duedate): void {
        $notifications = $this->get_due_notifications($userid);
        $matching = array_filter($notifications, function ($notification) use ($title): bool {
            return strpos($notification->fullmessage, $title) !== false;
        });

        $this->assertCount(1, $matching);
        $notification = reset($matching);
        $this->assertStringContainsString($title, $notification->fullmessage);
        $this->assertStringContainsString($boardname, $notification->fullmessage);
        $this->assertStringContainsString(
            userdate($duedate, get_string('strftimedate', 'langconfig')),
            $notification->fullmessage
        );
        $this->assertNotEmpty($notification->fullmessagehtml);
    }

    /**
     * Get a card field from the database.
     *
     * @param int $cardid Card id.
     * @param string $field Field name.
     * @return mixed
     */
    private function get_card_field(int $cardid, string $field) {
        global $DB;

        return $DB->get_field('kanbanlearning_card', $field, ['id' => $cardid]);
    }
}
