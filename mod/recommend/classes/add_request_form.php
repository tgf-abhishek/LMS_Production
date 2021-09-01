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
 * Contains class mod_recommend_add_request_form
 *
 * @package    mod_recommend
 * @copyright  2016 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir.'/formslib.php');

/**
 * Class mod_recommend_add_request_form
 *
 * @package    mod_recommend
 * @copyright  2016 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_recommend_add_request_form extends moodleform {
    /**
     * Form definition
     */
    public function definition() {
        $manager = $this->_customdata['manager'];

        $mform = $this->_form;

        $canadd = $manager->can_add_request();

        $mform->addElement('hidden', 'action', 'addrequest');
        $mform->setType('action', PARAM_ALPHA);
        $mform->addElement('hidden', 'id', $manager->get_cm()->id);
        $mform->setType('id', PARAM_INT);

        $instructions = get_string('requestinstructions', 'recommend', $canadd);
        if ($canadd > 1) {
            $instructions .= '<br>'.get_string('requestinstructionsmult', 'recommend');
        }

        $mform->addElement('static', 'instructions', '', $instructions);

        for ($i = 1; $i <= $canadd; $i++) {
            $mform->addElement('header', 'recommend'.$i, get_string('recommendationtitle', 'mod_recommend', $i));
            $mform->addElement('text', 'name'.$i, get_string('recommendatorname', 'recommend'));
            $mform->setType('name'.$i, PARAM_TEXT);
            $mform->addElement('text', 'email'.$i, get_string('email'));
            $mform->setType('email'.$i, PARAM_RAW_TRIMMED); // Will be validated later.
        }

        $this->add_action_buttons();
    }

    /**
     * Form validation
     *
     * @param array $data array of ("fieldname"=>value) of submitted data
     * @param array $files array of uploaded files "element_name"=>tmp_file_path
     * @return array of "element_name"=>"error_description" if there are errors,
     *         or an empty array if everything is OK (true allowed for backwards compatibility too).
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $manager = $this->_customdata['manager'];
        $emails = array_map(function($record) {
            return strtolower($record->email);
        }, $manager->get_requests());
        $newemails = [];

        foreach ($data as $key => $value) {
            if (preg_match('/^email\d+$/', $key) && strlen($value)) {
                if (clean_param($value, PARAM_EMAIL) !== $value) {
                    $errors[$key] = get_string('error_emailnotvalid', 'mod_recommend');
                } else if (in_array(strtolower($value), $emails)) {
                    $errors[$key] = get_string('error_emailused', 'mod_recommend');
                } else if (in_array(strtolower($value), $newemails)) {
                    $errors[$key] = get_string('error_emailduplicated', 'mod_recommend');
                } else {
                    $newemails[] = strtolower($value);
                }
            }
            if (preg_match('/^name(\d+)$/', $key, $matches) && strlen(trim($value))) {
                if (empty($data['email'.$matches[1]])) {
                    $errors[$key] = get_string('error_emailmissing', 'mod_recommend');
                }
            }
        }
        return $errors;
    }
}
