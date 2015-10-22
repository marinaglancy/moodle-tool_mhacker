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
        $tabs[] = new tabobject('stringhacker', new moodle_url('/admin/tool/mhacker/stringhacker.php'),
                get_string('stringhacker', 'tool_mhacker'));
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

    /**
     * Displays the list of plugins and core components with string files
     */
    public static function show_stringfiles_list() {
        global $CFG;
        $plugintypes = array('core' => 'core') + core_component::get_plugin_types();
        echo '<ul class="pluginslist">';
        foreach ($plugintypes as $plugintype => $directory) {
            echo "<li>".$plugintype."</li>";
            if ($plugintype === 'core') {
                $plugins = core_component::get_core_subsystems() + array('moodle' => 'moodle');
            } else {
                $plugins = core_component::get_plugin_list($plugintype);
            }
            ksort($plugins);
            echo '<ul class="stringfiles">';
            foreach ($plugins as $plugin => $plugindir) {
                $name = ($plugintype === 'core') ? $plugin : ($plugintype."_".$plugin);
                $filename = ($plugintype === 'mod' || $plugintype === 'core') ? $plugin : ($plugintype."_".$plugin);

                $plugindir = ($plugintype !== 'core') ? $plugindir : $CFG->dirroot;
                if (file_exists($plugindir . '/lang/en/'. $filename.'.php')) {
                    $url = new moodle_url('/admin/tool/mhacker/stringhacker.php', array('plugin' => $name));
                    echo "<li>".html_writer::link($url, $name)."</li>";
                }
            }
            echo "</ul>";
        }
        echo '</ul>';
    }

    /**
     * Parses string file and returns the chunks of text
     *
     * @param string $filepath
     * @return array()
     */
    protected static function parse_stringfile($filepath) {
        $lines = file($filepath);
        $chunks = array(array('', ''));
        $eof = false;
        foreach ($lines as $line) {
            if (!strlen(trim($line)) || preg_match('|^\s*\/\/|', $line)) {
                if (strlen($chunks[count($chunks)-1][1]) && preg_match('/;$/', $chunks[count($chunks)-1][0])) {
                    $chunks[] = array($line, '');
                } else {
                    $chunks[count($chunks)-1][0] .= $line;
                }
                if (preg_match('/deprecated/i', $line)) {
                    $eof = true;
                }
            } else if (!$eof && preg_match('/^\s*\$string\[(.*?)\]/', $line, $matches)) {
                $chunks[] = array($line, trim($matches[1], "'\""));
            } else {
                $chunks[count($chunks)-1][0] .= $line;
            }
        }

        $string = array();
        include($filepath);

        $stringkeys = array_keys($string);
        $i = 0;
        foreach ($chunks as $idx => $chunk) {
            if (strlen($chunk[1])) {
                $chunks[$idx][2] = $stringkeys[$i];
                $chunks[$idx][3] = $string[$stringkeys[$i]];
                $i++;
            } else {
                $chunks[$idx][2] = '';
                $chunks[$idx][3] = '';
            }
        }
        return $chunks;
    }

    /**
     * Sorts strings in language file alphabetically
     *
     * @param string $pluginname
     * @param bool $writechanges - write changes to file
     * @param string $addkey string to add (key)
     * @param string $addvalue string to add (value)
     * @return false|string false if sorting is not possible or new file contents otherwise
     */
    public static function sort_stringfile($pluginname, $writechanges = false, $addkey = null, $addvalue = null) {
        $filepath = self::find_stringfile_path($pluginname);
        if ($filepath === false || !is_writable($filepath)) {
            return false;
        }
        $chunks = self::parse_stringfile($filepath);
        $before = $after = '';
        if (!strlen($chunks[0][1])) {
            $before = $chunks[0][0];
            array_shift($chunks);
        }
        if (!strlen($chunks[count($chunks)-1][1])) {
            $after = $chunks[count($chunks)-1][0];
            array_pop($chunks);
        }
        $tosort = array();
        foreach ($chunks as $chunk) {
            if ($chunk[1] !== $chunk[2]) {
                // Key mismatch, file unsortable.
                return false;
            }
            if (!strlen($chunk[1]) && !strlen(trim($chunk[0]))) {
                // Skip empty line.
                continue;
            }
            $tosort[$chunk[1]] = trim($chunk[0]) . "\n";
        }
        if ($addkey) {
            if (array_key_exists($addkey, $tosort)) {
                return false;
            }
            $addvaluequoted = str_replace("'", "\'", str_replace("\\", "\\\\", $addvalue));
            $addkeyquoted = str_replace("'", "\'", str_replace("\\", "\\\\", $addkey));
            $tosort[$addkey] = "\$string['$addkeyquoted'] = '$addvaluequoted';\n";
        }
        ksort($tosort);
        $content = $before . join('', $tosort) . $after;
        if ($writechanges) {
            file_put_contents($filepath, $content);
            if ($addkey) {
                purge_all_caches();
            }
        }
        return $content;
    }

    /**
     * Given plugin name finds the string file path
     *
     * @param string $pluginname
     * @return string
     */
    protected static function find_stringfile_path($pluginname) {
        global $CFG;
        $matches = array();
        if (preg_match('/^(\w+)_(.*)$/', $pluginname, $matches)) {
            $plugins = core_component::get_plugin_list($matches[1]);
            if (!array_key_exists($matches[2], $plugins)) {
                return false;
            }
            $name = ($matches[1] === 'mod') ? $matches[2] : $pluginname;
            $filepath = $plugins[$matches[2]] . '/lang/en/' . $name . '.php';
        } else {
            $filepath = $CFG->dirroot . '/lang/en/' . $pluginname . '.php';
        }
        if (!file_exists($filepath)) {
            return false;
        }
        return $filepath;
    }

    /**
     * Displays the contents of the string file
     *
     * @param string $pluginname
     */
    public static function show_stringfile($pluginname) {
        $filepath = self::find_stringfile_path($pluginname);

        echo "<div>";
        if (!is_writable($filepath)) {
            echo 'File is not writable';
        } else if (self::sort_stringfile($pluginname) !== false) {
            $baseurl = new moodle_url('/admin/tool/mhacker/stringhacker.php');
            $url = new moodle_url($baseurl, array('plugin' => $pluginname,
                'action' => 'sort', 'sesskey' => sesskey()));
            echo html_writer::link($url, 'Re-sort strings in the file') . "<br>";
            echo html_writer::start_tag('form', array('method' => 'POST', 'action' => $baseurl));
            echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'plugin', 'value' => $pluginname));
            echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'action', 'value' => 'addstring'));
            echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()));
            echo html_writer::empty_tag('input', array('type' => 'text', 'name' => 'stringkey', 'value' => ''));
            echo html_writer::empty_tag('input', array('type' => 'text', 'name' => 'stringvalue', 'value' => ''));
            echo html_writer::empty_tag('input', array('type' => 'submit', 'name' => 'go', 'value' => 'Add string'));
            echo html_writer::end_tag('form');
        } else {
            echo 'File has an error and can not be re-sorted';
        }
        echo "</div>";

        $chunks = self::parse_stringfile($filepath);

        $string = array();
        include($filepath);
        $table = new html_table();
        $table->attributes['class'] .= ' stringslist';
        $table->colclasses = array('stringkey', 'stringvalue', '');
        $lastkey = null;
        foreach ($chunks as $chunk) {
            $table->data[] = $row = new html_table_row(array($chunk[2], $chunk[3], '<pre>'.$chunk[0].'</pre>'));
            $key = $chunk[2];
            if ($key) {
                if (strcmp($key, $lastkey) < 0) {
                    $row->attributes['class'] = 'sorterror';
                }
                if ($key !== $chunk[1]) {
                    $row->attributes['class'] = 'keymismatch';
                }
                $lastkey = $key;
            }
        }
        echo html_writer::table($table);
    }
}