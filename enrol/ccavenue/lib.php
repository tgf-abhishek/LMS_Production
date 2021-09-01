<?php

if (defined('DOM_BZ_PATH_PG_MAIN_201')== false) 
{
	define("DOM_BZ_PATH_PG_MAIN_201",$CFG->dirroot.'/enrol/ccavenue/common/');
}
$file_bz_dom = DOM_BZ_PATH_PG_MAIN_201."cbdom_main.php"; 

if (file_exists($file_bz_dom)) {
	include_once($file_bz_dom);
}  

class enrol_ccavenue_plugin extends enrol_plugin {

	public function get_currencies() {
        
		if (class_exists('Cbdom_main')) {
			$cbdom = new Cbdom_main();
			$currencyList = $cbdom->getAllowedCurrencyList();
			$currencies = array();
			foreach ($currencyList as $c) {
				$currencies[$c] = new lang_string($c, 'core_currencies');
			}
			
		}
		else
		{
			$currencies = array('USD' => 'US Dollars',
                                'INR' => 'Indian Rupees'
                             );
		}

        return $currencies;
    }
    public function get_info_icons(array $instances) {
        return array(new pix_icon('icon', get_string('pluginname', 'enrol_ccavenue'), 'enrol_ccavenue'));		
	}

    public function roles_protected() {
        return false;
    }

    public function allow_unenrol(stdClass $instance) {
        return true;
    }

    public function allow_manage(stdClass $instance) {
        return true;
    }

    public function show_enrolme_link(stdClass $instance) {
        return ($instance->status == ENROL_INSTANCE_ENABLED);
    }


    public function add_course_navigation($instancesnode, stdClass $instance) {
        if ($instance->enrol !== 'ccavenue') {
             throw new coding_exception('Invalid enrol instance type!');
        }

        $context = context_course::instance($instance->courseid);
        if (has_capability('enrol/ccavenue:config', $context)) {
            $managelink = new moodle_url('/enrol/ccavenue/edit.php', array('courseid'=>$instance->courseid, 'id'=>$instance->id));
            $instancesnode->add($this->get_instance_name($instance), $managelink, navigation_node::TYPE_SETTING);
        }
    }

    public function get_action_icons(stdClass $instance) {
        global $OUTPUT;

        if ($instance->enrol !== 'ccavenue') {
            throw new coding_exception('invalid enrol instance!');
        }
        $context = get_context_instance(CONTEXT_COURSE, $instance->courseid);

        $icons = array();

        if (has_capability('enrol/ccavenue:config', $context)) {
            $editlink = new moodle_url("/enrol/ccavenue/edit.php", array('courseid'=>$instance->courseid, 'id'=>$instance->id));
            $icons[] = $OUTPUT->action_icon($editlink, new pix_icon('i/edit', get_string('edit'), 'core', array('class'=>'icon')));
        }

        return $icons;
    }


    public function get_newinstance_link($courseid) {
        $context = get_context_instance(CONTEXT_COURSE, $courseid, MUST_EXIST);

        if (!has_capability('moodle/course:enrolconfig', $context) or !has_capability('enrol/ccavenue:config', $context)) {
            return NULL;
        }

        // multiple instances supported - different cost for different roles
        return new moodle_url('/enrol/ccavenue/edit.php', array('courseid'=>$courseid));
    }

    function enrol_page_hook(stdClass $instance) {
	
        global $CFG, $USER, $OUTPUT, $PAGE, $DB;
        ob_start();
 
		if (!class_exists('Cbdom_main')) {
			get_string('ccavenue_error');
			return ob_get_clean();
		}
		$cbdom = new Cbdom_main();
        if ($DB->record_exists('user_enrolments', array('userid'=>$USER->id, 'enrolid'=>$instance->id))) {
            return ob_get_clean();
        }

        if ($instance->enrolstartdate != 0 && $instance->enrolstartdate > time()) {
            return ob_get_clean();
        }

        if ($instance->enrolenddate != 0 && $instance->enrolenddate < time()) {
            return ob_get_clean();
        }
		
		/// Get course
        $course 	= $DB->get_record('course', array('id'=>$instance->courseid));	
        $context 	= context_course::instance($course->id);
        $shortname 	= format_string($course->shortname, true, array('context' => $context));
        $strloginto = get_string("loginto", "", $shortname);
        $strcourses = get_string("courses");

        // Pass $view=true to filter hidden caps if the user cannot see them
        if ($users = get_users_by_capability($context, 'moodle/course:update', 'u.*', 'u.id ASC',
                                             '', '', '', '', false, true)) {
            $users 	 = sort_by_roleassignment_authority($users, $context);
            $teacher = array_shift($users);
        } else {
            $teacher = false;
        }
		if ( (float) $instance->cost <= 0 ) {
            $cost = (float) $this->get_config('cost');
        } else {
            $cost = (float) $instance->cost;
        }

        if (!($course = $DB->get_record('course', array('id'=>$instance->courseid)))) {
		    echo '<p>'.get_string('invalidcourseid', 'enrol_ccavenue').'</p>';		
		}
        else if (abs($cost) < 0.01) { // no cost, other enrolment methods (instances) should be used
            echo '<p>'.get_string('nocost', 'enrol_ccavenue').'</p>';
        } else {

            if (isguestuser()) { // force login only for guest user, not real users with guest role
                if (empty($CFG->loginhttps)) {
                    $wwwroot = $CFG->wwwroot;
                } else {
                    // This actually is not so secure ;-), 'cause we're
                    // in unencrypted connection...
                    $wwwroot = str_replace("http://", "https://", $CFG->wwwroot);
                }
                echo '<div class="mdl-align"><p>'.get_string('paymentrequired').'</p>';
                echo '<p><b>'.get_string('cost').": $instance->currency $cost".'</b></p>';
                echo '<p><a href="'.$wwwroot.'/login/">'.get_string('loginsite').'</a></p>';
                echo '</div>';
            } else {
			
                //Sanitise some fields before building the ccavenue form
				$countries_list  = get_string_manager()->get_list_of_countries();
                $coursefullname  = format_string($course->fullname, true, array('context'=>$context));
                $courseshortname = $shortname;	
				$course_id		 = $course->id;
				$user_id 		 = '';
				$course_details  = "course id # ".$course->id ."  , Course name is ".$coursefullname;
                $userfullname    = fullname($USER);
                $userfirstname   = $USER->firstname;
                $userlastname    = $USER->lastname;
                $useraddress     = $USER->address;
                $usercity        = $USER->city;
				$userphone		 = $USER->phone1;
				$country_code	 = $USER->country;
				$country		 = $countries_list[$country_code];
				$email			 = $USER->email;
                $instancename    = $this->get_instance_name($instance);
				$order_id 		 = $instance->courseid."_".time();
				$merchant_param  = $USER->id."-".$course->id."-".$instance->id;
				$MerchantId   	 = $this->get_config('ccavenuemerchantid');
				$WorkingKey    	 = $this->get_config('ccavenueworkingkey');
				$access_code	 = $this->get_config('ccavenueaccesscode');
				$Amount		     = $cost;				
				//$redirect_url    = $CFG->wwwroot."/enrol/ccavenue/return.php";
				$redirect_url    = "https://tejgyan.tv/enrol/ccavenue/return.php";
				$currency_code	 = $instance->currency;
				$cancel_url	  	 = $CFG->wwwroot."/enrol/ccavenue/return.php";
				
				$user_language	 = current_language(); 			
				$cbdom 			 = new Cbdom_main();
				$language 		 = $cbdom->getAllowedLanguage($user_language);			

				
				$merchant_data_array = array();
				$merchant_data_array = array(
										'merchant_id' 		=> $MerchantId,
										'order_id'			=> $order_id,
										'currency'			=> $currency_code,
										'amount'			=> $Amount,
										'redirect_url'		=> $redirect_url,
										'cancel_url'		=> $cancel_url,
										'language'			=> $language,
										'billing_name'		=> $userfullname,
										'billing_address'	=> $useraddress,
										'billing_city'		=> $usercity,
										'billing_state'		=> '', 
										'billing_zip'		=> '',
										'billing_country'	=> $country,
										'billing_tel'		=> $userphone,
										'billing_email'		=> $email,
										'delivery_name'		=> $userfullname,
										'delivery_address'	=> $useraddress,
										'delivery_city'		=> $usercity,
										'delivery_state'	=> '',
										'delivery_zip'		=> '',
										'delivery_country'	=> $country,
										'delivery_tel'		=> $userphone,
										'merchant_param1'	=> $course_details,
										'merchant_param2'	=> $merchant_param,  
										'merchant_param3'	=> $MerchantId  										 
										);
			
				$merchant_data			= implode("&",$merchant_data_array);
				$data['access_code']	= $access_code ;				
				$db_prefix				= $DB->get_prefix();						
				
				$apidetails_result = new stdClass();

				$apidetails_result->a_id = '1';
				$apidetails_result->license_id = '1';
				$apidetails_result->user_id = '0';
				$apidetails_result->license_key = 'FREE';
				$apidetails_result->pgmodule_version = '2.0';
				$apidetails_result->cms = 'Moodle';
				$apidetails_result->cms_version = '3.3';
				$apidetails_result->ccversion = 'MCPG-2.';

				$show_form				= false;
				$sitedata 				= array();
				
				if (!is_object($apidetails_result)){
					$show_form=false;
				}
				else
				{
					if($apidetails_result->a_id > 0)
					{
						$sitedata =get_object_vars($apidetails_result);
						$show_form=true;
					}	
				}
				$passdata 	 = array("merchantdata"=>$merchant_data_array,"encryptkey"=>$WorkingKey,"data"=>$data);
				$passdata 	 = json_encode($passdata);
				if($show_form == false)
				{
					$api_resonse = get_string('ccavenue_auth_error', 'enrol_ccavenue');	
				}
				else
				{
					$api_resonse = $cbdom->getfrontformSubmitHtml($sitedata,$passdata);
				}
				include($CFG->dirroot.'/enrol/ccavenue/enrol.html');				
            }
        }
       return $OUTPUT->box(ob_get_clean());
    }
}
