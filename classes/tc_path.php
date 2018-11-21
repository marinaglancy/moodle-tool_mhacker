<?php
/**
 * Created by PhpStorm.
 * User: marina
 * Date: 19/11/2018
 * Time: 14:36
 */

/**
 * Handles one path being validated (file or directory)
 *
 * @package    local_moodlecheck
 * @copyright  2012 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_mhacker_tc_path {
    protected $tc;
    protected $path;
    protected $file = null;
    protected $subpaths = null;
    protected $treebuilt = false;
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

    public function get_full_path() {
        return $this->tc->get_full_path() . $this->path;
    }

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

    public function is_file() {
        return $this->file !== null;
    }

    public function is_dir() {
        return $this->subpaths !== null;
    }

    /**
     * @return tool_mhacker_tc_file
     */
    public function get_file() {
        return $this->file;
    }

    /**
     * @return self[]
     */
    public function get_subpaths() {
        return $this->subpaths;
    }

    protected function set_rootpath($rootpath) {
        $this->rootpath = (boolean)$rootpath;
    }

    public function is_rootpath() {
        return $this->rootpath;
    }

    public function add_check_points() {
        $this->build_tree();
        if ($this->is_file()) {
            $this->get_file()->add_check_points();
        } else if ($this->is_dir()) {
            foreach ($this->get_subpaths() as $subpath) {
                $subpath->add_check_points();
            }
        }
    }

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
