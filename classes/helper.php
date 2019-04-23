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
        $tabs[] = new tabobject('testcoverage', new moodle_url('/admin/tool/mhacker/testcoverage.php'),
            get_string('testcoverage', 'tool_mhacker'));
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
        $baseurl = new moodle_url('/admin/tool/mhacker/stringhacker.php');
        self::display_selector($baseurl);

        $plugintypes = self::get_plugins();
        echo '<ul class="pluginslist">';
        foreach ($plugintypes as $plugintype => $plugins) {
            echo "<li>".$plugintype."</li>";
            echo '<ul class="stringfiles">';
            foreach ($plugins as $plugin => $plugindir) {
                $name = ($plugintype === 'core') ? $plugin : ($plugintype."_".$plugin);
                $filename = ($plugintype === 'mod' || $plugintype === 'core') ? $plugin : ($plugintype."_".$plugin);

                $plugindir = ($plugintype !== 'core') ? $plugindir : $CFG->dirroot;
                if (file_exists($plugindir . '/lang/en/'. $filename.'.php')) {
                    $url = new moodle_url($baseurl, array('plugin' => $name));
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

        $keys = [];
        foreach ($chunks as $chunk) {
            if ($chunk[1]) {
                if (in_array($chunk[1], $keys)) {
                    \core\notification::add('String "'.$chunk[1].'"" is repeated in the string file! Please remove manually!');
                } else {
                    $keys[] = $chunk[1];
                }
            }
        }

        $string = array();
        include($filepath);

        // Validating.
//        $parsedkeys = array_filter(array_map(function($chunk) { return $chunk[1]; }, $chunks));
//        if ($extraparsed = array_diff($parsedkeys, array_keys($string))) {
//            \core\notification::add('There are extra parsed keys: '.join(', ', $extraparsed));
//        }
//        if ($extrastrings = array_diff(array_keys($string), $parsedkeys)) {
//            \core\notification::add('Could not parse the strings: '.join(', ', $extrastrings));
//        }
//        echo "<pre>".join(', ', $parsedkeys)."\n".join(', ', array_keys($string));

        $stringkeys = array_keys($string);
        $i = 0;
        foreach ($chunks as $idx => $chunk) {
            if (strlen($chunk[1])) {
                if (!array_key_exists($i, $stringkeys)) {
                    echo "Error, $i does not exist {$chunk[1]}<br>";
                }
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
     * Add comments to the end of each string in the given string file
     * @param string $filepath
     */
    protected static function add_comments_to_strings($filepath) {
        $stringfiles = self::find_all_stringfile_paths($filepath);
        foreach ($stringfiles as $stringfile) {
            self::add_comments_to_string_file($stringfile);
        }
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
    protected static function add_comments_to_string_file($filepath) {
        if (!is_writable($filepath)) {
            return false;
        }
        $pluginname = basename($filepath, '.php');
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
            $tosort[$chunk[1]] = trim($chunk[0]) . " //mhacker_str[{$chunk[1]},$pluginname]\n";
        }
        $content = $before . join('', $tosort) . $after;
        file_put_contents($filepath, $content);
        return $content;
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
    public static function sort_stringfile($pluginname, $writechanges = false, $addkey = null, $addvalue = null, $replacements = []) {
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
            if (array_key_exists($chunk[1], $replacements)) {
                $tosort[$chunk[1]] = trim($replacements[$chunk[1]]) . "\n";
            } else {
                $tosort[$chunk[1]] = trim($chunk[0]) . "\n";
            }
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
                get_string_manager()->reset_caches();
            }
        }
        return $content;
    }

    public static function replace_strings($pluginname, $strings) {
        $tempdir = make_temp_directory('mhacker');
        $tempfile = tempnam($tempdir, 's');
        file_put_contents($tempfile, "<?php\n". $strings);
        $chunks = self::parse_stringfile($tempfile);
        $replacements = [];
        foreach ($chunks as $chunk) {
            $replacements[$chunk[1]] = $chunk[0];
        }
        unlink($tempfile);
        if (!$replacements) {
            return false;
        }

        self::sort_stringfile($pluginname, true, null, null, $replacements);
        return true;
    }

    /**
     * Given plugin name finds the string file path
     *
     * @param string $pluginname
     * @return string
     */
    public static function find_stringfile_path($pluginname) {
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
     * Flat list of all files in the directory (recursive)
     * @param string $dir
     * @param array $results
     * @return array
     */
    protected static function get_dir_contents($dir, &$results = array()){
        $files = scandir($dir);

        foreach($files as $key => $value){
            $path = realpath($dir.DIRECTORY_SEPARATOR.$value);
            if(!is_dir($path)) {
                $results[] = $path;
            } else if($value != "." && $value != "..") {
                self::get_dir_contents($path, $results);
                $results[] = $path;
            }
        }

        return $results;
    }

    /**
     * Find all string files in the path
     *
     * Usually returns one file /lang/en/pluginame.php but can be multiple if there are embedded subplugins
     * @param string $pluginpath
     * @return array
     */
    protected static function find_all_stringfile_paths($pluginpath) {
        global $CFG;
        $allfiles = self::get_dir_contents($CFG->dirroot.'/'.$pluginpath);
        $stringfiles = array_filter($allfiles, function($filename) {
            return preg_match('|/lang/en/\w*\.php$|', $filename);
        });
        return $stringfiles;
    }

    /**
     * Displays the contents of the string file
     *
     * @param string $pluginname
     */
    public static function show_stringfile($pluginname) {
        global $CFG, $OUTPUT;
        require_once($CFG->libdir.'/tablelib.php');

        $filepath = self::find_stringfile_path($pluginname);
        $canwrite = false;

        echo "<div>";
        if (!is_writable($filepath)) {
            echo $OUTPUT->notification(get_string('filenotwritable', 'tool_mhacker', $filepath));
        } else if (self::sort_stringfile($pluginname) !== false) {
            $baseurl = new moodle_url('/admin/tool/mhacker/stringhacker.php');
            $url = new moodle_url($baseurl, array('plugin' => $pluginname,
                'action' => 'sort', 'sesskey' => sesskey()));
            echo html_writer::link($url, get_string('resortstrings', 'tool_mhacker')) . "<br>";
            echo html_writer::start_tag('form', array('method' => 'POST', 'action' => $baseurl));
            echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'plugin', 'value' => $pluginname));
            echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'action', 'value' => 'addstring'));
            echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()));
            echo html_writer::empty_tag('input', array('type' => 'text', 'name' => 'stringkey', 'value' => ''));
            echo html_writer::empty_tag('input', array('type' => 'text', 'name' => 'stringvalue', 'value' => ''));
            echo html_writer::empty_tag('input', array('type' => 'submit', 'name' => 'go', 'value' => 'Add string'));
            echo html_writer::end_tag('form');
            $canwrite = true;
        } else {
            echo get_string('filereadingerror', 'tool_mhacker');
        }
        echo "</div>";

        $chunks = self::parse_stringfile($filepath);

        $table = new flexible_table('tool_mhacker_stringtable');
        $table->define_baseurl(new moodle_url('/admin/tool/mhacker/stringhacker.php', array('plugin' => $pluginname)));
        $table->define_columns(array('stringkey', 'stringvalue', 'source'));
        $table->define_headers(array(get_string('stringkey', 'tool_mhacker'),
            get_string('stringvalue', 'tool_mhacker'),
            get_string('stringsource', 'tool_mhacker')));
        $table->set_attribute('class', 'generaltable stringslist');
        $table->collapsible(true);

        $table->setup();
        $lastkey = null;
        foreach ($chunks as $chunk) {
            $row = array($chunk[2], $chunk[3], '<pre>'.$chunk[0].'</pre>');
            $key = $chunk[2];
            $class = '';
            if ($key) {
                if (strcmp($key, $lastkey) < 0) {
                    $class = 'sorterror';
                }
                if ($key !== $chunk[1]) {
                    $class = 'keymismatch';
                }
                $lastkey = $key;
            }
            $table->add_data($row, $class);
        }
        $table->finish_output();

        if ($canwrite) {
            echo "<br><br><h4>Replace strings</h4>";
            echo html_writer::start_tag('form', array('method' => 'POST', 'action' => $baseurl));
            echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'plugin', 'value' => $pluginname));
            echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'action', 'value' => 'replacestrings'));
            echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()));
            echo html_writer::tag('textarea', '', array('name' => 'strings', 'rows' => 8, 'cols' => 70)) . '<br>';
            echo html_writer::empty_tag('input', array('type' => 'submit', 'name' => 'go', 'value' => 'Add string'));
            echo html_writer::end_tag('form');

        }
    }

    /**
     * Displays the selector for the all components / addons
     * @param moodle_url $baseurl
     * @return bool
     */
    protected static function display_selector(moodle_url $baseurl) : bool {
        $all = optional_param('all', false, PARAM_BOOL);
        echo html_writer::tag('p',
            !$all ? '<a href="'.$baseurl.'?all=1">Show all components</a>' : '<a href='.$baseurl.'>Show addons only</a>',
            ['class' => 'mdl-right']);
        return $all;
    }

    protected static function get_plugins() {
        $all = optional_param('all', false, PARAM_BOOL);

        $plugintypes = array('core' => 'core') + core_component::get_plugin_types();
        $rv = [];
        foreach ($plugintypes as $plugintype => $directory) {
            if ($plugintype === 'core') {
                $plugins = $all ? (core_component::get_core_subsystems() + array('moodle' => 'moodle')) : [];
                if (!$all) {
                    continue;
                }
            } else {
                $standard = \core_plugin_manager::standard_plugins_list($plugintype) ?: [];
                $plugins = array_filter(core_component::get_plugin_list($plugintype),
                    function ($k) use ($all, $standard) {
                        return $all ? true : !in_array($k, $standard);
                    }, ARRAY_FILTER_USE_KEY);
            }
            if ($plugins) {
                ksort($plugins);
                $rv[$plugintype] = $plugins;
            }
        }
        return $rv;
    }

    /**
     * Displays the list of plugins and core components with string files
     */
    public static function show_testcoverage_list() {
        global $CFG;
        $baseurl = new moodle_url('/admin/tool/mhacker/testcoverage.php');
        self::display_selector($baseurl);

        $customurl = new moodle_url($baseurl, ['custom' => 1]);
        echo "<p><a href=\"$customurl\">Custom</a></p>";

        $plugintypes = self::get_plugins();
        echo '<ul class="pluginslist">';
        foreach ($plugintypes as $plugintype => $plugins) {
            echo "<li>".$plugintype."</li>";
            echo '<ul class="testcoveragefiles">';
            foreach ($plugins as $plugin => $plugindir) {
                $name = ($plugintype === 'core') ? $plugin : ($plugintype."_".$plugin);
                $filename = ($plugintype === 'mod' || $plugintype === 'core') ? $plugin : ($plugintype."_".$plugin);

                $plugindir = ($plugintype !== 'core') ? $plugindir : $CFG->dirroot;
                if (file_exists($plugindir . '/lang/en/'. $filename.'.php')) {
                    $url = new moodle_url($baseurl, array('plugin' => $name));
                    echo "<li>".html_writer::link($url, $name)."</li>";
                }
            }
            echo "</ul>";
        }
        echo '</ul>';
    }

    /**
     * Given plugin name finds the string file path
     *
     * @param string $pluginname
     * @return string
     */
    protected static function find_component_path($pluginname) {
        global $CFG;
        $matches = array();
        if (preg_match('/^(\w+)_(.*)$/', $pluginname, $matches)) {
            $plugins = core_component::get_plugin_list($matches[1]);
            if (!array_key_exists($matches[2], $plugins)) {
                return false;
            }
            return str_replace($CFG->dirroot . '/', '', $plugins[$matches[2]]);
        } else {
            $subsystems = core_component::get_core_subsystems();
            return array_key_exists($pluginname, $subsystems) ?
                str_replace($CFG->dirroot . '/', '', $subsystems[$pluginname]) :
                false;
        }
    }

    public static function show_testcoverage_custom($action) {
        global $CFG;
        $baseurl = new moodle_url('/admin/tool/mhacker/testcoverage.php');

        $paths = optional_param('paths', '', PARAM_RAW);

echo <<<EOF
<form method="POST" action="$baseurl">
<input type="hidden" name="custom" value="1">
<textarea name="paths" cols="50" rows="10">$paths</textarea>
<br><input type="radio" name="action" value="addnew" id="action1"> <label for="action1">Add checkpoints to all files</label>
<br><input type="radio" name="action" value="addstrings" id="action4"> <label for="action4">Add comments to strings</label>
<br><input type="radio" name="action" value="todos" id="action2"> <label for="action2">Replace with TODOs</label>
<br><input type="radio" name="action" value="removeall" id="action3"> <label for="action3">Remove all</label>
<br><input type="submit" value="Go" name="go">
</form>
EOF;

        $patharray = array_map('trim', preg_split('/ *\\n */', trim($paths), -1, PREG_SPLIT_NO_EMPTY));

        if ($action === 'addnew') {
            $cp = 0;
            foreach ($patharray as $path) {
                //echo "'$path'<br>";
                $tc = new tool_mhacker_test_coverage(trim($path), $cp);
                $tc->add_check_points();
                $cp = $tc->get_next_cp() - 1;
            }
            echo "<p>...Added checkpoints ...</p>";
        }

        if ($action === 'addstrings') {
            foreach ($patharray as $path) {
                self::add_comments_to_strings($path);
            }
            echo "<p>...Added strings ...</p>";
        }

        if ($action === 'todos') {
            foreach ($patharray as $path) {
                //echo "'$path'<br>";
                $tc = new tool_mhacker_test_coverage(trim($path));
                $tc->todos();
            }
            echo "<p>...Analysis finished ...</p>";
        }

        if ($action === 'removeall') {
            foreach ($patharray as $path) {
                //echo "'$path'<br>";
                $tc = new tool_mhacker_test_coverage(trim($path));
                $tc->remove_all_check_points();
            }
            echo "<p>....Removed....</p>";
        }

    }

    /**
     * Displays the test coverage for a file
     *
     * @param string $pluginname
     */
    public static function show_testcoverage_file($pluginname) {
        global $CFG;

        $filepath = self::find_component_path($pluginname);
        //echo "pluginname = $pluginname , path = $filepath<br>";

        $url = new moodle_url('/admin/tool/mhacker/testcoverage.php', ['plugin' => $pluginname, 'sesskey' => sesskey()]);
        echo <<<EOF
        <h3>How to calculate test coverage for plugin {$pluginname}:</h3>
<ol>
    <li>Make sure your plugin working directory is clean:
<pre>cd {$CFG->dirroot}/{$filepath}
git status</pre>
    </li>
    <li>
        <a href="{$url}&amp;action=addnew">Add checkpoints to all files</a><br/>&nbsp;
    </li>
    <li>
        If you want to test strings usage, <a href="{$url}&amp;action=addstrings">Add comments to strings</a><br/>&nbsp;
        Find function get_string_manager() in /lib/moodlelib.php and replace:<br>&nbsp;
        <pre>
- \$singleton = new core_string_manager_standard(\$CFG->langotherroot, \$CFG->langlocalroot, \$translist)
+ \$singleton = new <b>tool_mhacker_string_manager</b>(\$CFG->langotherroot, \$CFG->langlocalroot, \$translist)
</pre>
    </li>
    <li>
        Run all automated tests:
<pre>cd {$CFG->dirroot}
php admin/tool/phpunit/cli/init.php
php admin/tool/behat/cli/init.php
./vendor/bin/phpunit --testsuite {$pluginname}_testsuite
./vendor/bin/phpunit admin/tool/dataprivacy/tests/metadata_registry_test.php
./vendor/bin/phpunit lib/tests/externallib_test.php
./vendor/bin/phpunit privacy/tests/provider_test.php
php admin/tool/behat/cli/run.php --tags=@{$pluginname}
</pre>
    </li>
    <li>Now you can use "git diff" to see all remaining checkpointы. Write more tests, execute them as many times as you want.<br/>&nbsp;</li>
    <li><a href="{$url}&amp;action=todos">Replace remaining checkpoints with TODOs</a><br/>&nbsp;</li>
</ol>

<p>If you have too many results you can also <a href="{$url}&amp;action=removeall">Remove all checkpoints</a> in bulk</p>
<p>Note: There is no automated action to remove the strings comments yet</p>
EOF;

        if ($action = optional_param('action', null, PARAM_ALPHA)) {
            require_sesskey();
            if ($action === 'removeall') {
                $tc = new tool_mhacker_test_coverage($filepath);
                $tc->remove_all_check_points();
                echo "<p>....Removed....</p>";
            }
            if ($action === 'addnew') {
                $tc = new tool_mhacker_test_coverage($filepath);
                $tc->add_check_points();
                echo "<p>...Added checkpoints ...</p>";
            }
            if ($action === 'addstrings') {
                self::add_comments_to_strings($filepath);
                echo "<p>...Added strings ...</p>";
            }
            if ($action === 'todos') {
                $tc = new tool_mhacker_test_coverage($filepath);
                $tc->todos();
                echo "<p>...Analysis finished ...</p>";
            }
        }


    }
}