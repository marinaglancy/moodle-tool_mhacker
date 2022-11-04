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
 * rb file for tool_mhacker
 *
 * @package    tool_mhacker
 * @copyright  2022 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$action = optional_param('action', '', PARAM_ALPHANUMEXT);

admin_externalpage_setup('toolmhacker', '', null, '', ['pagelayout' => 'report']);

$baseurl = new moodle_url('/admin/tool/mhacker/rb.php');
$PAGE->set_url(new moodle_url($baseurl, []));
navigation_node::override_active_url(new moodle_url('/admin/tool/mhacker/index.php'));
$title = get_string('reportbuilder', 'reportbuilder');
$PAGE->set_title($title);
$PAGE->set_heading($SITE->fullname);
$PAGE->navbar->add($title, $baseurl);

if (!$CFG->debugdeveloper) {
    throw new moodle_exception('error_notdebugging', 'tool_mhacker');
}
if ($CFG->version < 2022041900) {
    throw new coding_exception('Moodle 4.0 or later required');
}

if ($action === 'generatedatasource') {
    $form3 = new \tool_mhacker\form\rbdatasource();
    $form3->set_data_for_dynamic_submission();
    if ($form3->get_data()) {
        $form3->process_dynamic_submission();
    }
} else if ($action === 'generateentity') {
    $form2 = new \tool_mhacker\form\rbentity2();
    $form2->set_data_for_dynamic_submission();
    if ($form2->get_data()) {
        $form2->process_dynamic_submission();
    }
} else {
    $form1 = new \tool_mhacker\form\rbentity1();
    if ($form1->get_data()) {
        $form1->process_dynamic_submission();
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading($title);

tool_mhacker_helper::print_tabs('rb');

if (isset($form1)) {
    $form1->display();
    $url = new moodle_url($baseurl, ['action' => 'generatedatasource']);
    echo "<p>".html_writer::link($url, 'Generate datasource')."</p>";
    echo "<p>&nbsp;<br><br><br><br></p>";
    echo "<p>&nbsp;<br><br><br><br></p>";
    echo "<p>&nbsp;<br><br><br><br></p>";
} else if (isset($form2)) {
    $form2->display();
} else if (isset($form3)) {
    $form3->display();
}

echo $OUTPUT->footer();
