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

    public static function check_env($warn = false) {
        global $CFG;
        if (empty($CFG->tool_mhacker_test_coverage_file)) {
            // TODO fix it!
            $CFG->tool_mhacker_test_coverage_file = '/tmp/testcoverage_tenant';
        }
        if (!empty($CFG->tool_mhacker_test_coverage_file)) {
            $file = $CFG->tool_mhacker_test_coverage_file;
            if (file_exists($file) && is_writeable($file)) {
                return true;
            }
            if (!file_exists($file)) {
                $dir = dirname($file);
                if (file_exists($dir) && is_dir($dir) && is_writable($dir)) {
                    return true;
                }
            }
        } else {
            echo "no config\n";
        }
        if ($warn) {
            \core\notification::add('You must specify $CFG->tool_mhacker_test_coverage_file as a path to a writeable file in your config.php');
        }
        return false;
    }

    public function add_check_points() {
        global $DB;
        $cprun = $DB->insert_record('tool_mhacker_run',
            ['timecreated' => time(), 'path' => $this->path, 'maxid' => 0]);
        $path = new tool_mhacker_tc_path($this->path, []);
        if (!$path->is_writeable()) {
            return false;
        }
        $cp = 1;
        $path->add_check_points($cprun, $cp);
        $DB->update_record('tool_mhacker_run', ['id' => $cprun, 'maxid' => $cp]);
        return $cprun;
    }

    public function remove_all_check_points() {
        $path = new tool_mhacker_tc_path($this->path, []);
        if (!$path->is_writeable()) {
            return;
        }
        $path->remove_check_points();
    }

    public function analyze() {
        global $DB;
        $path = new tool_mhacker_tc_path($this->path, []);
        if (!$path->is_writeable()) {
            return;
        }

        $cprun = $DB->get_field_sql("SELECT max(id) FROM {tool_mhacker_run}");
        if (!$cprun) {
            return;
        }
        self::file_to_db($cprun);
        $list = $DB->get_fieldset_sql("SELECT DISTINCT cp FROM {tool_mhacker_log} WHERE runid = ? ORDER BY cp", [$cprun]);
        echo "Removing checkpoints ".join(', ', $list);
        $path->remove_check_points($list);
        // TODO replace remaining checkpoints with TODO comments.
    }

    public static function cp($cprun, $cp, $prereq = []) {
        global $CFG;
        if ((defined('PHPUNIT_TEST') && PHPUNIT_TEST) || defined('BEHAT_SITE_RUNNING')) {
            if (self::check_env()) {
                file_put_contents($CFG->tool_mhacker_test_coverage_file, time().",$cprun,$cp\n", FILE_APPEND);
            }
        }
    }

    protected static function file_to_db($cprun) {
        global $CFG, $DB;
        $handle = fopen($CFG->tool_mhacker_test_coverage_file, "r");
        if ($handle) {
            while (($line = fgets($handle)) !== false) {
                $x = preg_split('/,/', trim($line));
                if ((int)$x[1] == $cprun) {
                    $DB->insert_record('tool_mhacker_log', ['runid' => $cprun, 'cp' => $x[2]]);
                }
            }

            fclose($handle);
        }
    }

    public function get_files() {

    }
}