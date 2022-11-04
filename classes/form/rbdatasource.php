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
use core_reportbuilder\local\entities\base;
use core_reportbuilder\manager;
use moodle_url;
use tool_mhacker\rbgenerator;

/**
 * Reportbuilder datasource form
 *
 * @package     tool_mhacker
 * @copyright   2022 Marina Glancy
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rbdatasource extends \core_form\dynamic_form {

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
        $data = (array)$this->get_data();
        $data['entities'] = [];
        for ($i = 0; $i < 10; $i++) {
            if (!empty($data['entity_'.$i])) {
                $data['entities'][] = $data['entity_'.$i];
            }
            unset($data['entity_'.$i]);
        }
        $gen = new rbgenerator((object)$data);
        $gen->save_datasource();
        echo "OK";
        exit;
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

        $allentities = ['' => ''] + $this->get_all_entities();
        $form = $this->_form;

        $form->addElement('hidden', 'action', $this->optional_param('action', '', PARAM_ALPHANUMEXT));
        $form->setType('action', PARAM_ALPHANUMEXT);

        $form->addElement('text', 'classname', 'Datasource class name to generate');
        $form->setType('classname', PARAM_ALPHANUMEXT);

        $plugins = $this->get_all_plugins();
        $form->addElement('autocomplete', 'component', 'Component', ['' => ''] + array_combine($plugins, $plugins));
        $form->setType('component', PARAM_COMPONENT);

        for ($i = 0; $i < 10; $i++) {
            $form->addElement('select', 'entity_'.$i, 'Entity '.($i + 1), $allentities);
        }

        $this->add_action_buttons(true, 'Generate');
    }

    /**
     * List of all plugins
     *
     * @return array
     */
    protected function get_all_plugins() {
        $res = [];
        foreach (\core_component::get_core_subsystems() as $subsystem => $dir) {
            if ($dir) {
                $res[] = "core_$subsystem";
            }
        }
        foreach (\core_component::get_plugin_types() as $type => $unused) {
            foreach (\core_component::get_plugin_list($type) as $plugin => $unused2) {
                $res[] = "{$type}_{$plugin}";
            }
        }
        return $res;
    }

    /**
     * List of avialable entities
     *
     * @return array
     */
    protected function get_all_entities() {
        $instances = [];
        $classes1 = \core_component::get_component_classes_in_namespace(null, 'reportbuilder\\local\\entities');
        $classes2 = \core_component::get_component_classes_in_namespace(null, 'local\\entities');
        foreach (array_merge(array_keys($classes1), array_keys($classes2)) as $classname) {
            if (is_subclass_of($classname, base::class)) {
                $reflectionclass = new \ReflectionClass($classname);
                if (!$reflectionclass->isAbstract()) {
                    try {
                        $title = rbgenerator::call_protected_entity_method($classname, 'get_default_entity_title');
                    } catch (\Throwable $t) {
                        continue;
                    }
                    $instances[$classname] = $title . ' - ' .$classname;
                }
            }
        }

        return $instances;
    }
}
