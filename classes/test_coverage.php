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
class tool_mhacker_test_coverage {
    /** @var string */
    protected $path;
    /** @var array */
    protected $lines = null;
    /** @var int */
    protected $cp = 0;

    /**
     * Constructor
     *
     * @param string $path
     * @param int $cp
     */
    public function __construct($path, $cp = 0) {
        $this->path = $path;
        if (preg_match('|^(.*?):(.*?)$|', $path, $matches)) {
            $this->path = trim($matches[1]);
            $this->lines = preg_split('/ *, */', trim($matches[2]));
        }
        $this->cp = $cp;
    }

    /**
     * todo comment
     *
     * @return string
     */
    public function todo_comment() {
        return '// TODO ' . 'Not covered by automated tests.';
    }

    /**
     * get next checkpoint
     *
     * @return int
     */
    public function get_next_cp() {
        return ++$this->cp;
    }

    /**
     * get lines
     *
     * @return array
     */
    public function get_lines() {
        return $this->lines;
    }

    /**
     * add check points
     *
     * @return void
     */
    public function add_check_points() {
        $path = new tool_mhacker_tc_path($this);
        if (!$path->is_writeable()) {
            return;
        }
        $path->add_check_points();
    }

    /**
     * remove all check points
     *
     * @return void
     */
    public function remove_all_check_points() {
        $path = new tool_mhacker_tc_path($this);
        if (!$path->is_writeable()) {
            return;
        }
        $path->remove_check_points();
    }

    /**
     * todos
     *
     * @return void
     */
    public function todos() {
        $path = new tool_mhacker_tc_path($this);
        if (!$path->is_writeable()) {
            return;
        }
        $list = $path->replace_check_points_with_todos();
        echo "<pre>".join("\n", $list)."</pre>";
    }

    /**
     * checkpoint
     *
     * @param int $cp
     * @param array $prereq
     * @return void
     */
    public static function cp($cp, $prereq = []) {
        if ((defined('PHPUNIT_TEST') && PHPUNIT_TEST) || defined('BEHAT_SITE_RUNNING')) {
            $backtrace = debug_backtrace();
            tool_mhacker_tc_file::remove_check_point_from_path($backtrace[0]['file'], [$cp]);
        }
    }

    /**
     * get path
     *
     * @return string
     */
    public function get_path() {
        return $this->path;
    }

    /**
     * get full path
     *
     * @return string
     */
    public function get_full_path() {
        global $CFG;
        return $CFG->dirroot. '/'. $this->path;
    }

    /**
     * is file ignored
     *
     * @param string $filepath
     * @return bool
     */
    public function is_file_ignored($filepath) {
        $file = basename($filepath);
        if ($file === '.git' || $file === '.hg') {
            return true;
        }

        if (is_dir($this->get_full_path() . $filepath)) {
            if ($filepath === '/tests') {
                return true;
            }
        } else {
            $pathinfo = pathinfo($filepath);
            if (empty($pathinfo['extension']) || ($pathinfo['extension'] != 'php' && $pathinfo['extension'] != 'inc')) {
                return true;
            }

            if ($filepath === '/version.php' || $filepath === '/db/upgrade.php' || $filepath === '/db/access.php'
                    || $filepath === '/db/tag.php' || $filepath === '/db/tasks.php' || $filepath === '/db/subplugins.php'
                    || preg_match('|/lang/en/|', $filepath)) {
                return true;
            }
        }

        return false;
    }

    /**
     * is function ignored
     *
     * @param string $filepath
     * @param stdClass $function
     * @return bool
     */
    public function is_function_ignored($filepath, $function) {
        if (preg_match('|/classes/event/|', $filepath) &&
            in_array($function->name, ['get_objectid_mapping', 'get_other_mapping'])) {
            return true;
        }
        return false;
    }
}
