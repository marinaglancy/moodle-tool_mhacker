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
 * dbhacker file for tool_mhacker
 *
 * @package    tool_mhacker
 * @copyright  2015 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$tablename = optional_param('table', null, PARAM_NOTAGS);

admin_externalpage_setup('toolmhacker', '', null, '', array('pagelayout' => 'report'));

$baseurl = new moodle_url('/admin/tool/mhacker/dbhacker.php');
$PAGE->set_url(new moodle_url($baseurl, array('tablename' => $tablename)));
navigation_node::override_active_url(new moodle_url('/admin/tool/mhacker/index.php'));
$title = get_string('dbhacker', 'tool_mhacker');
$PAGE->set_title($title);
$PAGE->set_heading($SITE->fullname);
$PAGE->navbar->add($title, $baseurl);

if (!$CFG->debugdeveloper) {
    print_error('error_notdebugging', 'tool_mhacker');
}

$tables = $DB->get_tables();
if ($tablename && array_key_exists(strtolower($tablename), $tables)) {
    $PAGE->navbar->add($tablename);
}

echo $OUTPUT->header();
echo $OUTPUT->heading($title);

tool_mhacker_helper::print_tabs('dbhacker');

if (!$tablename || !array_key_exists(strtolower($tablename), $tables)) {
    tool_mhacker_helper::show_tables_list($tables);
} else {
    $backlink = html_writer::link($baseurl, get_string('back'));
    echo html_writer::div($backlink);
    tool_mhacker_helper::browse_db_table($tablename);
}

echo $OUTPUT->footer();
