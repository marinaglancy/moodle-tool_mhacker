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
 * Handles one path being validated (file or directory)
 *
 * @package    tool_mhacker
 * @copyright  2012 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_mhacker_tc_path {
    /** @var tool_mhacker_test_coverage  */
    protected $tc;
    /** @var string  */
    protected $path;
    /** @var tool_mhacker_tc_file  */
    protected $file = null;
    /** @var self[] */
    protected $subpaths = null;
    /** @var bool  */
    protected $treebuilt = false;
    /** @var bool */
    protected $rootpath = true;

    /**
     * tool_mhacker_tc_path constructor.
     *
     * @param tool_mhacker_test_coverage $tc
     * @param string $path
     */
    public function __construct(tool_mhacker_test_coverage $tc, string $path = '') {
        $this->tc = $tc;
        $this->path = $path;
    }

    /**
     * get full path
     *
     * @return string
     */
    public function get_full_path() {
        return $this->tc->get_full_path() . $this->path;
    }

    /**
     * build tree
     *
     * @return void
     */
    public function build_tree() {
        if ($this->treebuilt) {
            // Prevent from second validation.
            return;
        }
        if (is_file($this->get_full_path())) {
            $this->file = new tool_mhacker_tc_file($this->tc, $this->path);
        } else if (is_dir($this->get_full_path())) {
            $this->subpaths = array();
            if ($dh = opendir($this->get_full_path())) {
                while (($file = readdir($dh)) !== false) {
                    if ($file != '.' && $file != '..' && !$this->tc->is_file_ignored($this->path . '/' . $file)) {
                        $subpath = new tool_mhacker_tc_path($this->tc, $this->path . '/' . $file);
                        $subpath->set_rootpath(false);
                        $this->subpaths[] = $subpath;
                    }
                }
                closedir($dh);
            }
        }
        $this->treebuilt = true;
    }

    /**
     * is writable
     *
     * @return bool
     */
    public function is_writeable() {
        $writable = true;
        $this->build_tree();
        if ($this->is_file()) {
            if (!\is_writeable($this->get_file()->get_full_path())) {
                \core\notification::add('File '.$this->get_file()->get_full_path().' is not writable');
                $writable = false;
            }
        }
        if ($this->is_dir()) {
            foreach ($this->get_subpaths() as $subpath) {
                if (!$subpath->is_writeable()) {
                    $writable = false;
                }
            }
        }
        return $writable;
    }

    /**
     * is file
     *
     * @return bool
     */
    public function is_file() {
        return $this->file !== null;
    }

    /**
     * is dir
     *
     * @return bool
     */
    public function is_dir() {
        return $this->subpaths !== null;
    }

    /**
     * get file
     *
     * @return tool_mhacker_tc_file
     */
    public function get_file() {
        return $this->file;
    }

    /**
     * get subpaths
     *
     * @return self[]
     */
    public function get_subpaths() {
        return $this->subpaths;
    }

    /**
     * set root path
     *
     * @param string $rootpath
     * @return void
     */
    protected function set_rootpath($rootpath) {
        $this->rootpath = (boolean)$rootpath;
    }

    /**
     * is rootpath
     *
     * @return bool
     */
    public function is_rootpath() {
        return $this->rootpath;
    }

    /**
     * add check points
     *
     * @return void
     */
    public function add_check_points() {
        $this->build_tree();
        if ($this->is_file()) {
            if ($lines = $this->tc->get_lines()) {
                $this->get_file()->add_check_points_to_lines($lines);
            } else {
                $this->get_file()->add_check_points();
            }
        } else if ($this->is_dir()) {
            foreach ($this->get_subpaths() as $subpath) {
                $subpath->add_check_points();
            }
        }
    }

    /**
     * remove check points
     *
     * @param array|null $list
     * @return void
     */
    public function remove_check_points(array $list = null) {
        $this->build_tree();
        if ($this->is_file()) {
            $this->get_file()->remove_check_points($list);
        } else if ($this->is_dir()) {
            foreach ($this->get_subpaths() as $subpath) {
                $subpath->remove_check_points($list);
            }
        }
    }

    /**
     * remove check points with todos
     *
     * @return array
     */
    public function replace_check_points_with_todos() : array {
        $result = [];
        $this->build_tree();
        if ($this->is_file()) {
            $result = array_merge($result, $this->get_file()->replace_check_points_with_todos());
        } else if ($this->is_dir()) {
            foreach ($this->get_subpaths() as $subpath) {
                $result = array_merge($result, $subpath->replace_check_points_with_todos());
            }
        }
        return $result;
    }
}
