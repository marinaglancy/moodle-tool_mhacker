<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace tool_mhacker\form;

use context;
use core_component;
use moodle_url;

/**
 * Reportbuilder entity form1
 *
 * @package     tool_mhacker
 * @copyright   2022 Marina Glancy
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rbentity1 extends \core_form\dynamic_form {

    /**
     * Returns context where this form is used
     *
     * @return context
     */
    protected function get_context_for_dynamic_submission(): context {
        return \context_system::instance();
    }

    /**
     * Checks if current user has access to this form, otherwise throws exception
     *
     * @return void
     * @throws \required_capability_exception
     */
    protected function check_access_for_dynamic_submission(): void {
        require_capability('moodle/site:config', self::get_context_for_dynamic_submission());
    }

    /**
     * Process the form submission, used if form was submitted via AJAX
     *
     * This method can return scalar values or arrays that can be json-encoded, they will be passed to the caller JS.
     *
     * Submission data can be accessed as: $this->get_data()
     *
     * Example:
     *     $data = $this->get_data();
     *     file_postupdate_standard_filemanager($data, ....);
     *     api::save_entity($data); // Save into the DB, trigger event, etc.
     *
     * @return void
     */
    public function process_dynamic_submission() {
        $data = $this->get_data();
        redirect(new moodle_url('/admin/tool/mhacker/rb.php',
            ['action' => 'generateentity', 'tablename' => $data->tablename]));
    }

    /**
     * Set data
     */
    public function set_data_for_dynamic_submission(): void {
        null;
    }

    /**
     * Returns url to set in $PAGE->set_url() when form is being rendered or submitted via AJAX
     *
     * @return moodle_url
     * @throws \moodle_exception
     */
    protected function get_page_url_for_dynamic_submission(): moodle_url {
        return new moodle_url('/admin/tool/mhacker/db.php');
    }

    /**
     * Form definition
     */
    protected function definition() {
        $form = $this->_form;
        $alltables = array_keys(self::get_all_tables());
        sort($alltables);
        $tables = array_combine($alltables, $alltables);
        $form->addElement('autocomplete', 'tablename', 'Generate entity from table', ['' => ''] + $tables, []);
        $this->add_action_buttons(false, 'Next');
    }

    /**
     * Get all tables in the system
     *
     * @return array
     */
    public static function get_all_tables() {
        global $CFG;
        $alltables = [];
        $files = ['core' => $CFG->dirroot.'/lib/db/install.xml'];
        foreach (core_component::get_plugin_types() as $type => $unused) {
            $plugins = core_component::get_plugin_list($type);
            foreach ($plugins as $plugin => $fulldir) {
                $filename = $fulldir . '/db/install.xml';
                if (file_exists($filename)) {
                    $files["{$type}_{$plugin}"] = $filename;
                }
            }
        }
        foreach ($files as $pluginname => $filename) {
            $xmldbfile = new \xmldb_file($filename);
            $xmldbfile->loadXMLStructure();
            $structure = $xmldbfile->getStructure();
            $tables = $structure->getTables();
            foreach ($tables as $table) {
                $alltables[$table->getName()] = [$pluginname, $table];
            }
        }
        return $alltables;
    }
}
