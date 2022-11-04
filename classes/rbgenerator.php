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

namespace tool_mhacker;

use core_component;
use core_course\local\entities\course_category;
use core_reportbuilder\datasource;
use core_reportbuilder\local\entities\base;
use core_reportbuilder\local\filters\boolean_select;
use core_reportbuilder\local\filters\date;
use core_reportbuilder\local\filters\number;
use core_reportbuilder\local\filters\select;
use core_reportbuilder\local\filters\text;
use core_reportbuilder\local\helpers\format;
use core_reportbuilder\local\report\column;
use core_reportbuilder\local\report\filter;

/**
 * Reportbuilder generator
 *
 * @package     tool_mhacker
 * @copyright   2022 Marina Glancy
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rbgenerator {
    /** @var \stdClass */
    protected $data;
    /** @var array */
    protected $uses = [];
    /** @var array */
    protected $strings = [];

    /**
     * Constructor
     *
     * @param \stdClass $data
     */
    public function __construct(\stdClass $data) {
        $this->data = $data;
    }

    /**
     * Return text for the entity file
     *
     * @return string
     */
    public function out_entity(): string {
        global $OUTPUT;
        $this->add_uses(base::class);
        $this->add_uses(\lang_string::class);
        $this->add_string("entity_{$this->data->classname}", ucfirst($this->data->classname));
        return $OUTPUT->render_from_template('tool_mhacker/rb/entity', [
            'name' => $this->data->classname,
            'component' => $this->data->component,
            'tablename' => $this->data->tablename,
            'copyright' => '2022 Marina Glancy',
            'is_single_declaration' => 1,
            'columns' => $this->export_columns(),
            'filters' => $this->export_filters(),
            'uses' => $this->get_uses(),
        ]);
    }

    /**
     * Checks file is writeable
     *
     * @param string $file
     * @return void
     * @throws \coding_exception
     */
    protected function require_writeable(string $file) {
        if (!file_exists(dirname($file))) {
            mkdir(dirname($file), 0777, true);
        }
        if (file_exists($file)) {
            if (!is_writable($file)) {
                throw new \coding_exception("chmod 666 $file");
            }
        } else if (!is_writable(dirname($file))) {
            throw new \coding_exception("chmod 777 ".dirname($file));
        }
    }

    /**
     * Save entity
     *
     * @return void
     * @throws \coding_exception
     */
    public function save_entity() {
        $entity = $this->out_entity();
        $strings = $this->get_strings();

        $pdir = core_component::get_component_directory($this->data->component);
        $file = $pdir.'/classes/reportbuilder/local/entities/'.$this->data->classname.'.php';
        $langfile = $this->get_component_lang_file();
        $this->require_writeable($file);
        $this->require_writeable($langfile);
        file_put_contents($file, $entity);
        file_put_contents($langfile, $strings."\n", FILE_APPEND);
    }

    /**
     * Language file for the component
     *
     * @return string
     */
    protected function get_component_lang_file() {
        global $CFG;
        if (preg_match('/^core_(.*)$/', $this->data->component, $matches)) {
            $langfile = $CFG->dirroot.'/lang/en/'.$matches[1].'.php';
            if (!file_exists($langfile)) {
                $langfile = $CFG->dirroot.'/lang/en/moodle.php';
            }
        } else {
            $pdir = core_component::get_component_directory($this->data->component);
            $langfile = $pdir.'/lang/en/'.preg_replace('/^mod_/', '', $this->data->component).'.php';
        }
        return $langfile;
    }

    /**
     * Content of datasource file
     *
     * @return string
     */
    public function out_datasource(): string {
        global $OUTPUT;
        $this->add_uses(datasource::class);
        $this->add_string("datasource_{$this->data->classname}", ucfirst($this->data->classname));
        $entities = $this->export_datasource_entities();
        $defcolumns = $deffilters = [];
        foreach ($entities as $entity) {
            $defcolumns[] = $entity['firstcolumn'];
            $deffilters[] = $entity['firstfilter'];
        }
        return $OUTPUT->render_from_template('tool_mhacker/rb/datasource', [
            'name' => $this->data->classname,
            'component' => $this->data->component,
            'copyright' => '2022 Marina Glancy',
            'is_single_declaration' => 1,
            'entities' => $entities,
            'defaultcolumns' => $defcolumns,
            'defaultfilters' => $deffilters,
            'uses' => $this->get_uses(),
        ]);
    }

    /**
     * Content of test file
     *
     * @return string
     */
    protected function out_datasource_test(): string {
        global $OUTPUT;
        $this->uses = [];
        $this->add_uses(\core_reportbuilder_testcase::class);
        $entities = array_filter($this->data->entities);
        /** @var column[] $columns */
        $columns = self::call_protected_entity_method(reset($entities), 'get_all_columns');
        return $OUTPUT->render_from_template('tool_mhacker/rb/rbtest', [
            'name' => $this->data->classname,
            'component' => $this->data->component,
            'copyright' => '2022 Marina Glancy',
            'is_single_declaration' => 0,
            'firstcolumn' => $columns[0]->get_unique_identifier(),
            'uses' => $this->get_uses(),
        ]);
    }

    /**
     * Save datasource
     *
     * @return void
     * @throws \coding_exception
     */
    public function save_datasource() {
        $datasource = $this->out_datasource();
        $strings = $this->get_strings();
        $this->uses = [];
        $test = $this->out_datasource_test();

        $pdir = core_component::get_component_directory($this->data->component);
        $file = $pdir.'/classes/reportbuilder/datasource/'.$this->data->classname.'.php';
        $this->require_writeable($file);
        $langfile = $this->get_component_lang_file();
        $this->require_writeable($langfile);
        $testfile = $pdir.'/tests/reportbuilder/datasource/'.$this->data->classname.'_test.php';
        $this->require_writeable($testfile);
        file_put_contents($file, $datasource);
        file_put_contents($langfile, $strings."\n", FILE_APPEND);
        file_put_contents($testfile, $test);
    }

    /**
     * Add class to uses, returns alias
     *
     * @param string $classname
     */
    protected function add_uses(string $classname): string {
        if (!in_array($classname, $this->uses)) {
            $this->uses[] = $classname;
        }
        $x = preg_split('/\\\\/', $classname);
        return end($x);
    }

    /**
     * Get all 'uses' as string
     *
     * @return string
     */
    protected function get_uses(): string {
        return join("\n", array_map(function ($s) {
            return "use $s;";
        }, $this->uses));
    }

    /**
     * Add string
     *
     * @param string $identifier
     * @param string $value
     * @return void
     */
    protected function add_string(string $identifier, string $value) {
        $this->strings[$identifier] = $value;
    }

    /**
     * Get all strings
     *
     * @return string
     */
    public function get_strings(): string {
        $r = [];
        foreach ($this->strings as $identifier => $value) {
            $value = str_replace('"', '\\"', $value);
            $r[] = "\$string['$identifier'] = \"$value\";";
        }
        return join("\n", $r);
    }

    /**
     * Export columns
     *
     * @return array
     */
    protected function export_columns(): array {
        $res = [];
        foreach ($this->data->fields as $r) {
            $this->add_uses(column::class);
            if ($r['column']) {
                /** @var \xmldb_field $field */
                $field = $r['field'];
                $this->add_string($r['stringidentifier'], $r['label']);
                $res[] = [
                    'field' => $field->getName(),
                    'fielducfirst' => ucfirst($field->getName()),
                    'sortable' => $r['sortable'],
                    'stringidentifier' => $r['stringidentifier'],
                    'columntype' => $this->get_column_type($r['type']),
                    'callback' => $this->get_column_callback($field, $r['type'], !empty($r['forcecallback'])),
                ];
            }
        }

        return $res;
    }

    /**
     * Get column type
     *
     * @param int $type
     * @return string
     */
    protected function get_column_type(int $type) {
        switch ($type) {
            case column::TYPE_INTEGER:
                return 'TYPE_INTEGER';
            case column::TYPE_FLOAT:
                return 'TYPE_FLOAT';
            case column::TYPE_BOOLEAN:
                return 'TYPE_BOOLEAN';
            case column::TYPE_LONGTEXT:
                return 'TYPE_LONGTEXT';
            case column::TYPE_TEXT:
                return 'TYPE_TEXT';
            case column::TYPE_TIMESTAMP:
                return 'TYPE_TIMESTAMP';
        }
        return 'TYPE_TEXT';
    }

    /**
     * Get column callback
     *
     * @param \xmldb_field $field
     * @param int $type
     * @param bool $force
     * @return string
     */
    protected function get_column_callback(\xmldb_field $field, int $type, bool $force): string {
        if ($force || in_array($type, [column::TYPE_TIMESTAMP, column::TYPE_BOOLEAN, column::TYPE_FLOAT])) {
            $this->add_uses(\stdClass::class);
        }
        if ($type == column::TYPE_BOOLEAN) {
            $this->add_uses(format::class);
            return <<<EOL
static function(?int \$value, stdClass \$row): ?string {
                return is_null(\$value) ? null : format::boolean_as_text(\$value);
            }
EOL;
        } else if ($type == column::TYPE_TIMESTAMP) {
            $this->add_uses(format::class);
            return "[format::class, 'userdate']";
        } else if ($type == column::TYPE_FLOAT) {
            return <<<EOL
static function(\$value, stdClass \$row): ?string {
                return is_null(\$value) ? null : sprintf("%.2f", \$value);
            }
EOL;
        } else if ($force && $type == column::TYPE_INTEGER) {
            return <<<EOL
static function(?int \$value, stdClass \$row): ?string {
                return is_null(\$value) ? null : (string)\$value;
            }
EOL;
        } else if ($force) {
            return <<<EOL
static function(?string \$value, stdClass \$row): ?string {
                return \$value;
            }
EOL;
        }
        return '';
    }

    /**
     * Export filters
     *
     * @return array
     */
    protected function export_filters() {
        $res = [];
        foreach ($this->data->fields as $r) {
            $this->add_uses(filter::class);
            if (!empty($r['filter'])) {
                /** @var \xmldb_field $field */
                $field = $r['field'];
                $filtertype = $r['filtertype'] ?: $this->get_filter_type($r['type']);
                $typeclass = $this->add_uses($filtertype);
                $this->add_string($r['stringidentifier'], $r['label']);
                $res[] = [
                    'field' => $field->getName(),
                    'fielducfirst' => ucfirst($field->getName()),
                    'stringidentifier' => $r['stringidentifier'],
                    'typeclass' => $typeclass,
                    'isselect' => $filtertype === select::class,
                ];
            }
        }

        return $res;
    }

    /**
     * Get filter type
     *
     * @param int $type
     * @return string
     */
    protected function get_filter_type(int $type): string {
        if ($type == column::TYPE_TIMESTAMP) {
            return date::class;
        } else if ($type == column::TYPE_BOOLEAN) {
            return boolean_select::class;
        } else if ($type == column::TYPE_FLOAT || $type == column::TYPE_INTEGER) {
            return number::class;
        } else {
            return text::class;
        }
    }

    /**
     * Get datasource entieis
     *
     * @return array
     */
    protected function export_datasource_entities() {
        $res = [];
        $preventityname = null;
        foreach ($this->data->entities as $entityclass) {
            $classalias = $this->add_uses($entityclass);
            $entityname = preg_replace('/_/', '', $classalias);
            $table = $this->get_main_table_in_entity($entityclass);
            $columns = self::call_protected_entity_method($entityclass, 'get_all_columns');
            $filters = self::call_protected_entity_method($entityclass, 'get_all_filters');
            $res[] = [
                'entityname' => $entityname,
                'entityclass' => $classalias,
                'entitytable' => $table,
                'entitytableinbrackets' => '{'.$table.'}',
                'firstcolumn' => $columns[0]->get_unique_identifier(),
                'firstfilter' => $filters[0]->get_unique_identifier(),
                'preventityname' => $preventityname,
            ];
            $preventityname = $entityname;
        }

        return $res;
    }

    /**
     * Main table in the entity
     *
     * @param string $entityclass
     * @return int|string|null
     */
    protected function get_main_table_in_entity(string $entityclass) {
        if ($entityclass === course_category::class || $entityclass === \core_course\reportbuilder\local\entities\course_category::class) {
            return 'course_categories';
        } else {
            $tables = self::call_protected_entity_method($entityclass, 'get_default_table_aliases');
            return key($tables);
        }
    }

    /**
     * Call protected method on a class
     *
     * @param string $classname
     * @param string $method
     * @return mixed
     * @throws \ReflectionException
     */
    public static function call_protected_entity_method(string $classname, string $method) {
        $reflectionclass = new \ReflectionClass($classname);
        $rcp = $reflectionclass->getMethod($method);
        $rcp->setAccessible(true);
        /** @var base $instance */
        $instance = new $classname();
        return $rcp->invoke($instance);
    }
}
