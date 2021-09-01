<?php

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/formslib.php');

class enrol_ccavenue_edit_form extends moodleform {

    function definition() {
        $mform = $this->_form;

        list($instance, $plugin, $context) = $this->_customdata;

        $mform->addElement('header', 'header', get_string('pluginname', 'enrol_ccavenue'));

        $mform->addElement('text', 'name', get_string('custominstancename', 'enrol'));

        $options = array(ENROL_INSTANCE_ENABLED  => get_string('yes'),
                         ENROL_INSTANCE_DISABLED => get_string('no'));
        $mform->addElement('select', 'status', get_string('status', 'enrol_ccavenue'), $options);
        $mform->setDefault('status', $plugin->get_config('status'));

        $mform->addElement('text', 'cost', get_string('cost', 'enrol_ccavenue'), array('size'=>4));
        $mform->setDefault('cost', $plugin->get_config('cost'));
		
		$ccavenuecurrencies =  enrol_get_plugin('ccavenue')->get_currencies();
        $mform->addElement('select', 'currency', get_string('currency', 'enrol_ccavenue'), $ccavenuecurrencies);
        $mform->setDefault('currency', $plugin->get_config('currency'));

        if ($instance->id) {
            $roles = get_default_enrol_roles($context, $instance->roleid);
        } else {
            $roles = get_default_enrol_roles($context, $plugin->get_config('roleid'));
        }
        $mform->addElement('select', 'roleid', get_string('assignrole', 'enrol_ccavenue'), $roles);
        $mform->setDefault('roleid', $plugin->get_config('roleid'));
		
        $mform->addElement('duration', 'enrolperiod', get_string('enrolperiod', 'enrol_ccavenue'), array('optional' => true, 'defaultunit' => 86400));
        $mform->setDefault('enrolperiod', $plugin->get_config('enrolperiod'));
        $mform->addHelpButton('enrolperiod', 'enrolperiod', 'enrol_ccavenue');
		
        $mform->addElement('date_selector', 'enrolstartdate', get_string('enrolstartdate', 'enrol_ccavenue'), array('optional' => true));
        $mform->setDefault('enrolstartdate', 0);
        $mform->addHelpButton('enrolstartdate', 'enrolstartdate', 'enrol_ccavenue');

        $mform->addElement('date_selector', 'enrolenddate', get_string('enrolenddate', 'enrol_ccavenue'), array('optional' => true));
        $mform->setDefault('enrolenddate', 0);
        $mform->addHelpButton('enrolenddate', 'enrolenddate', 'enrol_ccavenue');

        $mform->addElement('hidden', 'id');
        $mform->addElement('hidden', 'courseid');

        $this->add_action_buttons(true, ($instance->id ? null : get_string('addinstance', 'enrol')));

        $this->set_data($instance);
    }

    function validation($data, $files) {
        global $DB, $CFG;
        $errors = parent::validation($data, $files);

        list($instance, $plugin, $context) = $this->_customdata;

        if ($data['status'] == ENROL_INSTANCE_ENABLED) {
            if (!empty($data['enrolenddate']) and $data['enrolenddate'] < $data['enrolstartdate']) {
                $errors['enrolenddate'] = get_string('enrolenddaterror', 'enrol_ccavenue');
            }

            if (!is_numeric($data['cost'])) {
                $errors['cost'] = get_string('costerror', 'enrol_ccavenue');

            }
        }
        return $errors;
    }
}