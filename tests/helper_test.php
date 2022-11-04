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

namespace tool_mhacker;

use tool_mhacker_helper;

/**
 * dbhacker file for tool_mhacker
 *
 * @package    tool_mhacker
 * @copyright  2022 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_mhacker_helper
 */
class helper_test extends \advanced_testcase {
    public function test_print_tabs() {
        ob_start();
        tool_mhacker_helper::print_tabs('dbhacker');
        $contents = ob_get_contents();
        $this->assertNotEmpty($contents);
        ob_end_clean();
    }

    public function test_find_stringfile_path() {
        global $CFG;
        $this->assertEquals($CFG->dirroot . '/admin/tool/mhacker/lang/en/tool_mhacker.php',
            tool_mhacker_helper::find_stringfile_path('tool_mhacker'));
        $this->assertEquals($CFG->dirroot . '/mod/assign/lang/en/assign.php',
            tool_mhacker_helper::find_stringfile_path('mod_assign'));
    }
}
