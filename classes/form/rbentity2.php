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
use core_reportbuilder\local\filters\boolean_select;
use core_reportbuilder\local\filters\course_selector;
use core_reportbuilder\local\filters\date;
use core_reportbuilder\local\filters\duration;
use core_reportbuilder\local\filters\number;
use core_reportbuilder\local\filters\select;
use core_reportbuilder\local\filters\text;
use core_reportbuilder\local\filters\user;
use core_reportbuilder\local\report\column;
use moodle_url;
use tool_mhacker\rbgenerator;

/**
 * Reportbuilder entity form1
 *
 * @package     tool_mhacker
 * @copyright   2022 Marina Glancy
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rbentity2 extends \core_form\dynamic_form {

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
        $this->get_table();
        $data = $this->get_data();
        $r = [
            'component' => $this->tableplugin,
            'classname' => $data->classname,
            'tablename' => $data->tablename,
            'fields' => $this->export_fields(),
        ];
        $gen = new rbgenerator((object)$r);
        $entity = $gen->out_entity();
        $strings = $gen->get_strings();

        $pdir = core_component::get_component_directory($this->tableplugin);
        $file = $pdir.'/classes/reportbuilder/local/entities/'.$data->classname.'.php';
        if (!file_exists(dirname($file))) {
            mkdir(dirname($file), 0777, true);
        }
        if (file_exists($file)) {
            if (!is_writable($file)) {
                throw new \coding_exception("chmod 666 $file");
            }
        } else if (!is_writable($pdir)) {
            throw new \coding_exception("chmod 777 $pdir");
        }
        $langfile = $pdir.'/lang/en/'.$this->tableplugin.'.php'; // TODO different for mod.
        if (!is_writable($langfile)) {
            throw new \coding_exception("chmod 666 $langfile");
        }
        file_put_contents($file, $entity);
        file_put_contents($langfile, $strings."\n", FILE_APPEND);
        echo "OK";
        exit;
    }

    /**
     * Export fields
     *
     * @return array
     */
    protected function export_fields() {
        $data = (array)$this->get_data();

        $fields = [];
        foreach ($this->get_fields() as $field) {
            $n = $field->getName();
            if (!empty($data["column_$n"])) {
                $fields[] = [
                    'field' => $field,
                    'column' => $data["column_$n"],
                    'type' => $data["type_$n"],
                    'sortable' => $data["sortable_$n"] ?? false,
                    'forcecallback_' => $data['forcecallback_'.$n] ?? 0,
                    'filter' => $data["filter_$n"],
                    'filtertype' => $data["filtertype_$n"] ?? '',
                    'label' => $data["label_$n"],
                    'stringidentifier' => $data["stringidentifier_$n"],
                ];
            }
        }
        return $fields;
    }

    /**
     * Load in existing data as form defaults
     *
     * Can be overridden to retrieve existing values from db by entity id and also
     * to preprocess editor and filemanager elements
     *
     * Example:
     *     $id = $this->optional_param('id', 0, PARAM_INT);
     *     $data = api::get_entity($id); // For example, retrieve a row from the DB.
     *     file_prepare_standard_filemanager($data, ...);
     *     $this->set_data($data);
     */
    public function set_data_for_dynamic_submission(): void {
        $data = [];
        $name = preg_replace('/^'.preg_quote($this->tableplugin, '/').'_/',
            '', $this->get_table()->getName());
        foreach ($this->get_fields() as $field) {
            $type = $this->detect_field_type($field);
            $n = $field->getName();
            $data["column_$n"] = 1;
            $data["filter_$n"] = 1;
            $data["type_$n"] = $type;
            $data["sortable_$n"] = (int)($type != column::TYPE_LONGTEXT);
            $data["label_$n"] = $field->getComment() ?: $n;
            $data["stringidentifier_$n"] = $name.'_'.$n;
        }
        $data['tablename'] = $this->get_table()->getName();
        $data['tablename_static'] = $this->get_table()->getName();
        $data['pluginname_static'] = $this->tableplugin;
        $data['classname'] = $name;
        $this->set_data($data);
    }

    /**
     * Detect field type
     *
     * @param \xmldb_field $field
     * @return int
     */
    protected function detect_field_type(\xmldb_field $field) {
        if ($field->getType() == XMLDB_TYPE_TEXT) {
            return column::TYPE_LONGTEXT;
        }
        if (in_array($field->getType(), [XMLDB_TYPE_FLOAT, XMLDB_TYPE_NUMBER, XMLDB_TYPE_INTEGER])) {
            if ($field->getDecimals() || $field->getType() == XMLDB_TYPE_FLOAT) {
                return column::TYPE_FLOAT;
            } else if (preg_match('/(time|date)/', $field->getName())) {
                return column::TYPE_TIMESTAMP;
            } else if ($field->getLength() <= 2) {
                return column::TYPE_BOOLEAN;
            } else {
                return column::TYPE_INTEGER;
            }
        }
        return column::TYPE_TEXT;
    }

    /**
     * Returns url to set in $PAGE->set_url() when form is being rendered or submitted via AJAX
     *
     * @return moodle_url
     * @throws \moodle_exception
     */
    protected function get_page_url_for_dynamic_submission(): moodle_url {
        return new moodle_url('/admin/tool/mhacker/db.php',
            ['action' => 'generateentity']);
    }

    /**
     * Form definition
     */
    protected function definition() {
        $form = $this->_form;
        $form->addElement('hidden', 'action', $this->optional_param('action', '', PARAM_ALPHANUMEXT));
        $form->setType('action', PARAM_ALPHANUMEXT);
        $form->addElement('hidden', 'tablename', $this->optional_param('tablename', '', PARAM_ALPHANUMEXT));
        $form->setType('tablename', PARAM_ALPHANUMEXT);

        $form->addElement('static', 'tablename_static', 'Table name', $this->get_table()->getName());
        $form->addElement('static', 'pluginname_static', 'Plugin', $this->tableplugin);
        $form->addElement('text', 'classname', 'Entity class name to generate');
        $form->setType('classname', PARAM_COMPONENT);

        foreach ($this->get_fields() as $field) {
            $this->add_field($field);
        }

        $this->add_action_buttons(true, 'Generate');
    }

    /** @var \xmldb_table */
    protected $table;
    /** @var string */
    protected $tableplugin;

    /**
     * Get the table
     *
     * @return mixed|\xmldb_table
     */
    public function get_table() {
        if ($this->table === null) {
            $tablename = $this->optional_param('tablename', '', PARAM_ALPHANUMEXT);
            [$this->tableplugin, $this->table] = rbentity1::get_all_tables()[$tablename];
        }
        return $this->table;
    }

    /**
     * Get all fields in the table (except for excluded)
     *
     * @return \xmldb_field[]
     */
    protected function get_fields() {
        return array_filter($this->get_table()->getFields(), function (\xmldb_field $f) {
            return $f->getName() !== 'id';
        });
    }

    /**
     * Add one DB table field to the form
     *
     * @param \xmldb_field $field
     * @return void
     */
    protected function add_field(\xmldb_field $field) {
        $n = $field->getName();
        $group = [];
        $typeoptions = [
            column::TYPE_INTEGER => 'INTEGER',
            column::TYPE_TEXT => 'TEXT',
            column::TYPE_TIMESTAMP => 'TIMESTAMP',
            column::TYPE_BOOLEAN => 'BOOLEAN',
            column::TYPE_FLOAT => 'FLOAT',
            column::TYPE_LONGTEXT => 'LONGTEXT',
        ];
        $allfilterclasses = [
           boolean_select::class,
           course_selector::class,
           date::class,
           duration::class,
           number::class,
           select::class,
           text::class,
           user::class,
        ];
        $filtertypeoptions = ['' => '--Auto--'];
        foreach ($allfilterclasses as $class) {
            $x = preg_split('/\\\\/', $class);
            $filtertypeoptions[$class] = end($x);
        }

        $group[] = $this->_form->createElement('checkbox', 'column_'.$n, 'Column');
        $group[] = $this->_form->createElement('select', 'type_'.$n, 'Type', $typeoptions);
        $group[] = $this->_form->createElement('checkbox', 'sortable_'.$n, 'Sortable');
        $group[] = $this->_form->createElement('checkbox', 'forcecallback_'.$n, 'Force callback');

        $group[] = $this->_form->createElement('checkbox', 'filter_'.$n, 'Filter');
        $group[] = $this->_form->createElement('select', 'filtertype_'.$n, 'Filter type', $filtertypeoptions);

        $group[] = $this->_form->createElement('text', 'label_'.$n, 'Label');
        $group[] = $this->_form->createElement('text', 'stringidentifier_'.$n, 'String name');

        $this->_form->addGroup($group, 'grp_'.$n, $n, ' ', false);

        $this->_form->setType('column_'.$n, PARAM_INT);
        $this->_form->setType('filter_'.$n, PARAM_INT);
        $this->_form->setType('sortable_'.$n, PARAM_INT);
        $this->_form->setType('label_'.$n, PARAM_RAW);
        $this->_form->setType('stringidentifier_'.$n, PARAM_ALPHANUMEXT);
        $this->_form->hideIf('sortable_'.$n, 'column_'.$n, 'notchecked');
        $this->_form->hideIf('filter_'.$n, 'column_'.$n, 'notchecked');
        $this->_form->hideIf('type_'.$n, 'column_'.$n, 'notchecked');
        $this->_form->hideIf('label_'.$n, 'column_'.$n, 'notchecked');
        $this->_form->hideIf('stringidentifier_'.$n, 'column_'.$n, 'notchecked');
        $this->_form->hideIf('forcecallback_'.$n, 'column_'.$n, 'notchecked');
        $this->_form->hideIf('filtertype_'.$n, 'column_'.$n, 'notchecked');
        $this->_form->hideIf('filtertype_'.$n, 'filter_'.$n, 'notchecked');
    }
}
