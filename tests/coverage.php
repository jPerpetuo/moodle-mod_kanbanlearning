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

defined('MOODLE_INTERNAL') || die();

/**
 * PHPUnit coverage configuration for mod_kanbanlearning.
 *
 * @package     mod_kanbanlearning
 * @copyright   2023-2025 ISB Bayern
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_kanbanlearning_coverage extends phpunit_coverage_info {
    /** @var array Additional production folders to include in coverage reports. */
    protected $includelistfolders = [
        'backup',
    ];

    /** @var array Additional production files to include in coverage reports. */
    protected $includelistfiles = [
        'board_action.php',
        'index.php',
        'mod_form.php',
        'settings.php',
        'view.php',
    ];
}

return new mod_kanbanlearning_coverage();
