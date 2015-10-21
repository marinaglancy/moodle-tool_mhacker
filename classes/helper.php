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
 * Helper class for tool_mhacker
 *
 * @package    tool_mhacker
 * @copyright  2015 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

/**
 * Helper funcitons for tool_mhacker
 *
 * @package    tool_mhacker
 * @copyright  2015 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_mhacker_helper {

    public static function print_tabs($currenttab) {
        global $OUTPUT;
        $tabs = array();
        $tabs[] = new tabobject('dbhacker', new moodle_url('/admin/tool/mhacker/dbhacker.php'),
                get_string('dbhacker', 'tool_mhacker'));
        echo $OUTPUT->tabtree($tabs, $currenttab);
    }

    /**
     * Displays list of db tables
     *
     * @param array $tables
     */
    public static function show_tables_list($tables) {
        global $DB;
        echo '<ul class="tableslist">';
        foreach ($tables as $tablename) {
            $url = new moodle_url('/admin/tool/mhacker/dbhacker.php', array('table' => $tablename));
            $urlparams = array();
            $count = $DB->count_records($tablename);
            $urlparams['class'] = $count ? 'nonemptytable' : 'emptytable';
            $tablenamedisplay = $tablename;
            if ($count) {
                $tablenamedisplay.=html_writer::span(" ($count)", 'rowcount');
            }
            echo '<li>'.html_writer::link($url, $tablenamedisplay, $urlparams).'</li>';
        }
        echo '</ul>';
    }

    /**
     * Display contents of one db table
     *
     * @param string $tablename
     */
    public static function browse_db_table($tablename) {
        global $OUTPUT, $CFG, $DB;
        require_once($CFG->libdir.'/tablelib.php');

        echo $OUTPUT->heading($tablename, 3);

        $columns = array_keys($DB->get_columns($tablename));

        $t = new table_sql('tool_mhacker_' . $tablename);
        $url = new moodle_url('/admin/tool/mhacker/dbhacker.php', array('table' => $tablename));
        $t->define_baseurl($url);
        $t->define_columns($columns);
        $t->define_headers($columns);
        $t->set_sql('*', '{'.$tablename.'}', '1=1', array());
        $t->out(20, false);
    }
}