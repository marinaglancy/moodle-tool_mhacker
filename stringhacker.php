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
 * string hacker file for tool_mhacker
 *
 * @package    tool_mhacker
 * @copyright  2015 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$pluginname = optional_param('plugin', null, PARAM_NOTAGS);
$action = optional_param('action', null, PARAM_NOTAGS);

admin_externalpage_setup('toolmhacker', '', null, '', array('pagelayout' => 'report'));

$baseurl = new moodle_url('/admin/tool/mhacker/stringhacker.php');
$PAGE->set_url($baseurl);
navigation_node::override_active_url(new moodle_url('/admin/tool/mhacker/index.php'));
$title = get_string('stringhacker', 'tool_mhacker');
$PAGE->set_title($title);
$PAGE->set_heading($SITE->fullname);
$PAGE->navbar->add($title, $baseurl);

if (!$CFG->debugdeveloper) {
    print_error('error_notdebugging', 'tool_mhacker');
}

if ($pluginname) {
    $PAGE->navbar->add($pluginname);
}

if ($pluginname && $action === 'sort') {
    require_sesskey();
    $result = tool_mhacker_helper::sort_stringfile($pluginname, true);
    redirect(new moodle_url($baseurl, array('plugin' => $pluginname)),
            ($result === false) ? get_string('errorstringsorting', 'tool_mhacker') :
            get_string('stringssorted', 'tool_mhacker'), 5);
}
if ($pluginname && $action === 'addstring') {
    require_sesskey();
    $stringkey = trim(required_param('stringkey', PARAM_ALPHANUMEXT));
    $result = tool_mhacker_helper::sort_stringfile($pluginname, true,
        $stringkey, required_param('stringvalue', PARAM_RAW));
    if ($result) {
        redirect(new moodle_url($baseurl, array('plugin' => $pluginname, 'added' => $stringkey)));
    }
    redirect(new moodle_url($baseurl, array('plugin' => $pluginname)),
            get_string('erroraddingstring', 'tool_mhacker'), 5);
}

echo $OUTPUT->header();
echo $OUTPUT->heading($title);

if ($pluginname && ($stringkey = optional_param('added', null, PARAM_ALPHANUMEXT)) &&
        get_string_manager()->string_exists($stringkey, $pluginname)) {

    $stringvalue = get_string($stringkey, $pluginname);
    $arg = preg_match('/\$a/', $stringvalue) ? ', $a' : '';
    $example = "get_string('$stringkey', '$pluginname'$arg)";
    $a = (object)array(
        'key' => $stringkey,
        'plugin' => $pluginname,
        'value' => $stringvalue,
        'example' => $example,
    );

    echo $OUTPUT->notification(get_string('stringadded', 'tool_mhacker', $a), 'notifysuccess');
}

tool_mhacker_helper::print_tabs('stringhacker');

if ($pluginname) {
    $backlink = html_writer::link($baseurl, get_string('back'));
    echo html_writer::div($backlink);
    tool_mhacker_helper::show_stringfile($pluginname);
} else {
    tool_mhacker_helper::show_stringfiles_list();
}

echo $OUTPUT->footer();
