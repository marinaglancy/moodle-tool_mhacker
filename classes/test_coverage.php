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
* Class tool_mhacker_test_coverage
*
* @package    tool_mhacker
* @copyright  2018 Marina Glancy
* @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/

defined('MOODLE_INTERNAL') || die;

/**
* Class tool_mhacker_test_coverage
*
* @package    tool_mhacker
* @copyright  2018 Marina Glancy
* @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/
class tool_mhacker_test_coverage {

    protected $path;

    public function __construct(string $path) {
        $this->path = $path;
    }

    protected static function get_log_file() {
        // TODO find a way not to hardcode it.
        return '/tmp/testcoverage_tenant';
    }

    public function todo_comment() {
        return '// TODO Not covered by automated tests.';
    }

    public static function check_env($warn = false) {
        $file = self::get_log_file();
        if (!file_exists($file)) {
            $dir = dirname($file);
            if (file_exists($dir) && is_dir($dir) && is_writable($dir)) {
                return true;
            }
            touch($file);
        }
        $fileperm = substr(sprintf('%o', fileperms($file)), -4);
        if (($fileperm === '0666') || chmod($file, 0666)) {
            return true;
        }
        if ($warn) {
            \core\notification::add("Make sure that log file $file exists and is has 666 write mode");
        }
        return false;
    }

    protected $cp = 0;
    public function get_next_cp() {
        return ++$this->cp;
    }

    public function add_check_points() {
        global $DB;
        $cprun = $DB->insert_record('tool_mhacker_run',
            (object)['timecreated' => time(), 'path' => $this->path, 'maxid' => 0]);
        $path = new tool_mhacker_tc_path($this);
        if (!$path->is_writeable()) {
            return false;
        }
        $this->cp = 0;
        $path->add_check_points($cprun);
        $DB->update_record('tool_mhacker_run', ['id' => $cprun, 'maxid' => $this->cp]);
        return $cprun;
    }

    public function remove_all_check_points() {
        $path = new tool_mhacker_tc_path($this);
        if (!$path->is_writeable()) {
            return;
        }
        $path->remove_check_points();
    }

    public function analyze() {
        global $DB;
        $path = new tool_mhacker_tc_path($this);
        if (!$path->is_writeable()) {
            return;
        }

        $cprun = $DB->get_field_sql("SELECT max(id) FROM {tool_mhacker_run}");
        if (!$cprun) {
            return;
        }
        self::file_to_db($cprun);
        $list = $DB->get_fieldset_sql("SELECT DISTINCT cp FROM {tool_mhacker_log} WHERE runid = ? ORDER BY cp", [$cprun]);
        echo "<p>The following checkpoints were covered by automated tests and are now removed: ".join(', ', $list).'</p>';
        $path->remove_check_points($list);

        $maxid = $DB->get_field('tool_mhacker_run', 'maxid', ['id' => $cprun]);
        $remaining = array_diff(array_keys(array_fill(1, $maxid, 1)), $list);
        echo "<p>The following checkpoints are remaining: ".join(', ', $remaining).'</p>';
    }

    public function todos() {
        global $DB;
        $path = new tool_mhacker_tc_path($this);
        if (!$path->is_writeable()) {
            return;
        }
        $list = $path->replace_check_points_with_todos();
        echo "<pre>".join("\n", $list)."</pre>";
    }

    public static function cp($cprun, $cp, $prereq = []) {
        if ((defined('PHPUNIT_TEST') && PHPUNIT_TEST) || defined('BEHAT_SITE_RUNNING')) {
            if (self::check_env()) {
                file_put_contents(self::get_log_file(), time().",$cprun,$cp\n", FILE_APPEND);
            }
        }
    }

    protected static function file_to_db($cprun) {
        global $DB;
        $handle = @fopen(self::get_log_file(), "r");
        if ($handle) {
            while (($line = fgets($handle)) !== false) {
                $x = preg_split('/,/', trim($line));
                if ((int)$x[1] == $cprun) {
                    $DB->insert_record('tool_mhacker_log', (object)['runid' => $cprun, 'cp' => $x[2]]);
                }
            }

            fclose($handle);
        }
    }

    public function get_path() {
        return $this->path;
    }

    public function get_full_path() {
        global $CFG;
        return $CFG->dirroot. '/'. $this->path;
    }

    public function is_file_ignored($filepath) {
        $file = basename($filepath);
        if ($file === '.git' || $file === '.hg') {
            return true;
        }

        if (is_dir($this->get_full_path() . $filepath)) {
            if ($filepath === '/tests') {
                //\core\notification::add('Skip path '.$filepath, \core\output\notification::NOTIFY_INFO);
                return true;
            }
        } else {
            $pathinfo = pathinfo($filepath);
            if (empty($pathinfo['extension']) || ($pathinfo['extension'] != 'php' && $pathinfo['extension'] != 'inc')) {
                //\core\notification::add('Skip file '.$filepath, \core\output\notification::NOTIFY_INFO);
                return true;
            }

            if ($filepath === '/version.php' || $filepath === '/db/upgrade.php' || $filepath === '/db/access.php'
                    || $filepath === '/db/tag.php' || $filepath === '/db/tasks.php' || $filepath === '/db/subplugins.php'
                    || preg_match('|/lang/en/|', $filepath)) {
                //\core\notification::add('Skip file '.$filepath, \core\output\notification::NOTIFY_INFO);
                return true;
            }
        }

        return false;
    }

    public function is_function_ignored($filepath, $function) {
        if (preg_match('|/classes/event/|', $filepath) &&
            in_array($function->name, ['get_objectid_mapping', 'get_other_mapping'])) {
            //\core\notification::add('Skip function '. $function->name .' in file '.$filepath, \core\output\notification::NOTIFY_INFO);
            return true;
        }
        return false;
    }
}