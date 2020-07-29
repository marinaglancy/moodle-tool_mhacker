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
 * Class tool_mhacker_string_manager
 *
 * @package     tool_mhacker
 * @copyright   2019 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Class tool_mhacker_string_manager
 *
 * @package     tool_mhacker
 * @copyright   2019 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_mhacker_string_manager extends core_string_manager_standard {

    /**
     * Get String returns a requested string
     *
     * @param string $identifier The identifier of the string to search for
     * @param string $component The module the string is associated with
     * @param string|object|array $a An object, string or number that can be used
     *      within translation strings
     * @param string $lang moodle translation language, null means use current
     * @return string The String !
     */
    public function get_string($identifier, $component = '', $a = null, $lang = null) {
        $str = parent::get_string($identifier, $component, $a, $lang);
        if (!((defined('PHPUNIT_TEST') && PHPUNIT_TEST) || defined('BEHAT_SITE_RUNNING'))) {
            return $str;
        }
        if (strlen($identifier) && strlen($component)) {
            if (($filepath = tool_mhacker_helper::find_stringfile_path($component)) && is_writable($filepath)) {
                $t = file_get_contents($filepath);
                $s = ' //mhacker_str['.$identifier.','.preg_replace('/^mod_/', '', $component).']';
                if (strpos($t, $s)) {
                    file_put_contents($filepath, str_replace($s, '', $t));
                }
            }
        }
        return $str;
    }
}
