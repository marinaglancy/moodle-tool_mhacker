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
    protected $path = null;
    protected $ignorepaths = null;
    protected $file = null;
    protected $subpaths = null;
    protected $treebuilt = false;
    protected $rootpath = true;

    public function __construct($path, $ignorepaths) {
        $path = trim($path);
        // If the path is already one existing full path
        // accept it, else assume it's a relative one.
        if (!file_exists($path) and substr($path, 0, 1) == '/') {
            $path = substr($path, 1);
        }
        $this->path = $path;
        $this->ignorepaths = $ignorepaths;
    }

    public function get_fullpath() {
        global $CFG;
        // It's already one full path.
        if (file_exists($this->path)) {
            return $this->path;
        }
        return $CFG->dirroot. '/'. $this->path;
    }

    public function build_tree() {
        if ($this->treebuilt) {
            // Prevent from second validation.
            return;
        }
        if (is_file($this->get_fullpath())) {
            $this->file = new tool_mhacker_tc_file($this->get_fullpath());
        } else if (is_dir($this->get_fullpath())) {
            $this->subpaths = array();
            if ($dh = opendir($this->get_fullpath())) {
                while (($file = readdir($dh)) !== false) {
                    if ($file != '.' && $file != '..' && $file != '.git'  && $file != '.hg' && !$this->is_ignored($file)) {
                        $subpath = new tool_mhacker_tc_path($this->path . '/'. $file, $this->ignorepaths);
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
            if (!\is_writeable($this->get_file()->get_filepath())) {
                \core\notification::add('File '.$this->get_file()->get_filepath().' is not writable');
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

    protected function is_ignored($file) {
        $filepath = $this->path. '/'. $file;
        foreach ($this->ignorepaths as $ignorepath) {
            $ignorepath = rtrim($ignorepath, '/');
            if ($filepath == $ignorepath || substr($filepath, 0, strlen($ignorepath) + 1) == $ignorepath . '/') {
                return true;
            }
        }
        return false;
    }

    public function is_file() {
        return $this->file !== null;
    }

    public function is_dir() {
        return $this->subpaths !== null;
    }

    public function get_path() {
        return $this->path;
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

    public function add_check_points($cprun, &$cp) {
        $this->build_tree();
        if ($this->is_file()) {
            $this->get_file()->add_check_points($cprun, $cp);
        } else if ($this->is_dir()) {
            foreach ($this->get_subpaths() as $subpath) {
                $subpath->add_check_points($cprun, $cp);
            }
        }
    }

    public function remove_check_points($list = null) {
        $this->build_tree();
        if ($this->is_file()) {
            $this->get_file()->remove_check_points($list);
        } else if ($this->is_dir()) {
            foreach ($this->get_subpaths() as $subpath) {
                $subpath->remove_check_points($list);
            }
        }
    }
}
