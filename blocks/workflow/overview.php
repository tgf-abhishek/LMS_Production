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
 * Provide an overview of a workflow.
 *
 * @package   block_workflow
 * @copyright 2011 Lancaster University Network Services Limited
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(dirname(__FILE__) . '/../../config.php');
require_once(dirname(__FILE__) . '/locallib.php');

// Get the submitted paramaters.
$contextid  = required_param('contextid', PARAM_INT);
$workflowid = required_param('workflowid', PARAM_INT);

// Determine the context and cm.
list($context, $course, $cm) = get_context_info_array($contextid);

// Require login.
require_login($course, false, $cm);

if ($cm) {
    $PAGE->set_cm($cm);
} else {
    $PAGE->set_context($context);
}

// Require the workflow:view capability.
require_capability('block/workflow:view', $PAGE->context);

// Set the page URL.
$PAGE->set_url('/blocks/workflow/overview.php', array('contextid' => $contextid, 'workflowid' => $workflowid));
$PAGE->set_pagelayout('standard');
$PAGE->set_course($course);

// Grab the workflow and states.
$workflow = new block_workflow_workflow($workflowid);
$stepstates = $workflow->step_states($contextid, $workflowid);
$tparams = array('contexttitle' => $context->get_context_name(), 'workflowname' => $workflow->name);

// Check that this workflow is assigned to this context.
$statelist = array_filter($stepstates, function ($a) {
    return isset($a->stateid);
});
if (count($statelist) == 0) {
    throw new block_workflow_not_assigned_exception(get_string('workflownotassignedtocontext', 'block_workflow', $tparams));
}

// Set the heading and page title.
$title = get_string('overviewtitle', 'block_workflow', $tparams);
$PAGE->set_heading($title);
$PAGE->set_title($title);

// Add the breadcrumbs.
$PAGE->navbar->add(get_string('blocks'));
$PAGE->navbar->add(get_string('pluginname', 'block_workflow'));
$PAGE->navbar->add($workflow->name);

// Grab the renderer.
$renderer = $PAGE->get_renderer('block_workflow');

// Display the page.
echo $OUTPUT->header();
echo $renderer->workflow_overview($workflow, $stepstates, $context);
echo $OUTPUT->footer();
