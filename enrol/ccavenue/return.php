<?php

require("../../config.php");
require_once("lib.php");
//require_once($CFG->libdir.'/eventslib.php');
require_once($CFG->libdir.'/deprecatedlib.php');
require_once($CFG->libdir.'/enrollib.php');
require_once($CFG->libdir . '/filelib.php');
require_once("$CFG->dirroot/enrol/ccavenue/lib.php");

if (defined('DOM_BZ_PATH_PG_MAIN_201')== false) 
{
	define("DOM_BZ_PATH_PG_MAIN_201",$CFG->dirroot.'/enrol/ccavenue/common/');
}
$file_bz_dom = DOM_BZ_PATH_PG_MAIN_201."cbdom_main.php"; 

if (file_exists($file_bz_dom)) {
	include_once($file_bz_dom);
}  



$req 			= 'cmd=_notify-validate';

if (empty($_POST) or !empty($_GET)) {
    print_error("Sorry, you can not use the script that way.");
}

$plugin     	 = enrol_get_plugin('ccavenue');
$WorkingKey 	 = $plugin->get_config('ccavenueworkingkey');	

if (!class_exists('Cbdom_main')) {
	redirect($CFG->wwwroot, get_string('ccavenue_error'));
}

$cbdom = new Cbdom_main();

$encResponse		= $_REQUEST['encResp'];			
$rcvdString			= $cbdom->decrypt($encResponse,$WorkingKey);
$decryptValues		= explode('&', $rcvdString);
$dataSize			= sizeof($decryptValues);
$response_array		= array();
for($i = 0; $i < count($decryptValues); $i++) 
{
	$information = explode('=',$decryptValues[$i]);
	if(count($information)==2)
	{
		$response_array[$information[0]] = urldecode($information[1]);
	}
}
$Order_Id		 = '';
$MerchantId		 = '';
$tracking_id 	 = '';
$bank_ref_no	 = '';
$order_status	 = '';
$failure_message = '';
$payment_mode	 = '';
$card_name		 = '';
$status_code	 = '';
$status_message	 = '';
$currency		 = '';
$amount			 = '';
$merchant_param1 = '';
$merchant_param2 = '';

if(isset($response_array['order_id'])) 			$Order_Id 			= $response_array['order_id'];
if(isset($response_array['tracking_id'])) 		$tracking_id 		= $response_array['tracking_id'];
if(isset($response_array['bank_ref_no'])) 		$bank_ref_no 		= $response_array['bank_ref_no'];
if(isset($response_array['order_status'])) 		$order_status		= $response_array['order_status'];
if(isset($response_array['failure_message'])) 	$failure_message	= $response_array['failure_message'];
if(isset($response_array['payment_mode'])) 		$payment_mode 		= $response_array['payment_mode'];
if(isset($response_array['card_name'])) 		$card_name 			= $response_array['card_name'];
if(isset($response_array['status_code'])) 		$status_code 		= $response_array['status_code'];
if(isset($response_array['status_message'])) 	$status_message 	= $response_array['status_message'];
if(isset($response_array['currency'])) 			$currency 			= $response_array['currency'];
if(isset($response_array['amount'])) 			$amount 			= $response_array['amount'];
if(isset($response_array['merchant_param1']))	$merchant_param1 	= $response_array['merchant_param1'];
if(isset($response_array['merchant_param2'])) 	$merchant_param2 	= $response_array['merchant_param2'];
if(isset($response_array['merchant_param3'])) 	$MerchantId 		= $response_array['merchant_param3'];

$supportemail 	= $CFG->supportemail;
$user 		= $response_array['billing_email'];
$subject		= 'CCAvenue MCPG Payment Status';
//$messagehtml = 'Thank you for registering this course . Your credit card has been charged and your transaction is successful';

$messagehtml = '
Dear '.$response_array['delivery_name'].',<br><br>  
											We have received your order, Thanks for your CCAvenue payment.The transaction was successful.Your payment is authorized.<br><br>
											The details of the order are below: <br><br>
											<table style="border-collapse:collapse">
											<tr style="border-bottom: 1px solid #cacaca;">
												<td style="padding:10px 10px 10px;border-right:1px solid #cacaca;">Order ID</td>
												<td style="padding:10px 10px 10px;">#'.$response_array['order_id'].'</td> 
											</tr>
											<tr style="border-bottom: 1px solid #cacaca;">
												<td style="padding:10px 10px 10px;border-right:1px solid #cacaca;">Date Ordered</td>
												<td style="padding:10px 10px 10px;">'.$response_array['trans_date'].'</td> 
											</tr>
											<tr style="border-bottom: 1px solid #cacaca;">
												<td style="padding:10px 10px 10px;border-right:1px solid #cacaca;vertical-align:top;">Payment Method</td>
												<td style="padding:10px 10px 10px;">CCAvenue MCGP</td> 
											</tr>
											<tr style="border-bottom: 1px solid #cacaca;">
												<td style="padding:10px 10px 10px;border-right:1px solid #cacaca;">Order Total</td>
												<td style="padding:10px 10px 10px;">'.$response_array['currency'].' '.$response_array['mer_amount'].'</td> 
											</tr>
											</table><br><br>

';//$response_array['billing_email']

$userid		= '';
$courseid	= '';
$instanceid	= '';	
$custom 	= explode('-', $merchant_param2);

if (isset($custom[0])) $userid      = (int)$custom[0];
if (isset($custom[1])) $courseid    = (int)$custom[1];
if (isset($custom[2])) $instanceid  = (int)$custom[2];

if (!$course = $DB->get_record("course", array("id"=>$courseid))) {
 	redirect($CFG->wwwroot);
}

$data 	= new stdClass();
$enrol_ccavenue_columns = array('order_id', 'merchant_id', 'billing_name', 'billing_address', 'billing_city', 'billing_state', 'billing_zip', 'billing_country', 'billing_tel', 'billing_email', 'notes', 'merchant_param1', 'merchant_param2', 'courseid', 'instanceid', 'userid', 'payment_status', 'timeupdated', 'timecreated', 'settletime', 'amount', 'currency');

foreach ($response_array as $key => $value) {
    $req .= "&$key=".urlencode($value);
	if (in_array(strtolower($key),$enrol_ccavenue_columns ))
	{
    	$enrol_ccavenue_column        = strtolower($key);	
		$data->$enrol_ccavenue_column = $value;
	}
} 
$data->userid           = $userid;
$data->courseid         = $courseid;
$data->instanceid       = $instanceid;
$data->timeupdated      = time();
$data->timecreated 		= time();
$data->settletime 		= time();
$data->merchant_id 		= $MerchantId;
$notes 					= '';

if(isset($response_array['tracking_id'])) 		$tracking_id 		= $response_array['tracking_id'];
if(isset($response_array['bank_ref_no'])) 		$bank_ref_no 		= $response_array['bank_ref_no'];
if(isset($response_array['order_status'])) 		$order_status		= $response_array['order_status'];
if(isset($response_array['failure_message'])) 	$failure_message	= $response_array['failure_message'];
if(isset($response_array['payment_mode'])) 		$payment_mode 		= $response_array['payment_mode'];
if(isset($response_array['card_name'])) 		$card_name 			= $response_array['card_name'];
if(isset($response_array['status_code'])) 		$status_code 		= $response_array['status_code'];
if(isset($response_array['status_message'])) 	$status_message 	= $response_array['status_message'];
if(isset($response_array['currency'])) 			$currency 			= $response_array['currency'];

$notes_array=array();
if(isset($response_array['tracking_id']) && $response_array['tracking_id']!='')
{
	$notes_array[]=  " tracking id :".$response_array['tracking_id'];
}
if(isset($response_array['bank_ref_no']) && $response_array['bank_ref_no']!='')
{
	$notes_array[]= " bank_ref no :".$response_array['bank_ref_no'];
}

if(isset($response_array['order_status']) && $response_array['order_status']!='')
{
	$notes_array[]=  " order status :".$response_array['order_status'];
}
if(isset($response_array['payment_mode']) && $response_array['payment_mode']!='')
{
	$notes_array[]=  " payment mode :".$response_array['payment_mode'];
}
if(isset($response_array['card_name']) && $response_array['card_name']!='')
{
	$notes_array[]=  " card name :".$response_array['card_name'];
}
if(isset($response_array['status_code']) && $response_array['status_code']!='')
{
	$notes_array[]=  " status code :".$response_array['status_code'];
}
 
if(isset($response_array['status_message']) && $response_array['status_message']!='')
{
	$notes_array[]= " status message :".$response_array['status_message'];
}

if(isset($response_array['failure_message']) && $response_array['failure_message']!='')
{
	$notes_array[]=  " failure_message :".$response_array['failure_message'];
}

$notes = implode("<br/>",$notes_array);
$data->notes 			= $notes;
$course_id          	= $data->courseid ;
$payment_message_array 	= array();

if(count($response_array)>0)
{
	if ($data->merchant_id != $plugin->get_config('ccavenuemerchantid')) {   // Check that the ccavenue merchant id  is the one we want it to be
		$payment_message_array[]= message_ccavenue_error_to_admin("merchant id is {$data->merchant_id} (not ".
		$plugin->get_config('ccavenuemerchantid').")", $data);
		
	}
	
	if (!$user = $DB->get_record('user', array('id'=>$data->userid))) {   // Check that user exists
		$payment_message_array[]= message_ccavenue_error_to_admin("User $data->userid doesn't exist", $data);
		
	}
	
	if (!$course = $DB->get_record('course', array('id'=>$data->courseid))) { // Check that course exists
		$payment_message_array[]=message_ccavenue_error_to_admin("Course $data->courseid doesn't exist", $data);
		
	}
	
	if (! $plugin_instance = $DB->get_record("enrol", array("id"=>$data->instanceid, "status"=>0))) {
		$payment_message_array[]=message_ccavenue_error_to_admin("Not a valid instance id", $data);
		
		
	}
	$coursecontext = context_course::instance($course->id);
	$data->currency = $plugin_instance->currency;
	
	if ( (float) $plugin_instance->cost <= 0 ) 
	{
		$cost = (float) $plugin->get_config('cost');
	} 
	else 
	{
		$cost = (float) $plugin_instance->cost;
	}
	
	if ($data->amount < $cost) 
	{
		$cost = format_float($cost, 2);
		$payment_message_array[]=message_ccavenue_error_to_admin("Amount paid is not enough ($data->amount < $cost))", $data);
		 
	}
	if ($plugin_instance->enrolperiod) {
		$timestart = time();
		$timeend   = $timestart + $plugin_instance->enrolperiod;
	} else {
		$timestart = 0;
		$timeend   = 0;
	}

	if($order_status === "Success")
	{
		email_to_user($user,$supportemail, $subject, $messagehtml, $messagehtml);
		$payment_message_array[] = "<br>Thank you for registering this course . Your credit card has been charged and your transaction is successful.";
		$data->payment_status 	 = 'Success';	
		
	}
	else if($order_status === "Aborted")
	{
		$payment_message_array[] = "<br>Thank you for registering this course.However,the transaction has been Aborted.";
		$data->payment_status 	 = 'UnSuccess'; 
	}
	else if($order_status === "Failure")
	{
		$payment_message_array[] = "<br>Thank you for registering this course.However,the transaction has been declined.";
		$data->payment_status 	 = 'UnSuccess'; 
	}
	else
	{
		echo($order_status);
		email_to_user($user,$supportemail, $subject, '', $messagehtml);
		$payment_message_array[] = "<br>moinSecurity Error. Illegal access detected";
		$data->payment_status 	 = 'Illegal Access';
	}
}
else
{
	$payment_message_array[] = "<br>Security Error. Illegal access detected";
	$data->payment_status 	 = 'Illegal Access';
	//Here you need to simply ignore this and dont need
	//to perform any operation in this condition
}	

$context = get_context_instance(CONTEXT_COURSE, $course->id, MUST_EXIST);

require_login();	
if (isset($SESSION->wantsurl)) {
	$destination = $SESSION->wantsurl;
	unset($SESSION->wantsurl);
} else {
	$destination = "$CFG->wwwroot/course/view.php?id=$course->id";
	
}
$fullname = format_string($course->fullname, true, array('context' => $context));
$a = new stdClass();
if(($data->payment_status == 'Success')  )
{
		$DB->insert_record("enrol_ccavenue", $data);

		// Enrol user
		$plugin->enrol_user($plugin_instance, $user->id,$plugin_instance->roleid, $timestart, $timeend);
		
        if ($users   = get_users_by_capability($context, 'moodle/course:update', 'u.*', 'u.id ASC',
                                             '', '', '', '', false, true)) {
            $users 	 = sort_by_roleassignment_authority($users, $context);
            $teacher = array_shift($users);
        } else {
            $teacher = false;
        }
		
        $mailstudents = $plugin->get_config('mailstudents');
		$mailteachers = $plugin->get_config('mailteachers');
        $mailadmins   = $plugin->get_config('mailadmins');
        $shortname 	  = format_string($course->shortname, true, array('context' => $context));		
 
        if( (!empty($mailstudents))and $teacher) {
		 
            $a->coursename 	= format_string($course->fullname, true, array('context' => $coursecontext));
            $a->profileurl 	= "$CFG->wwwroot/user/view.php?id=$user->id";
            $eventdata 		= new stdClass();
			
            $eventdata->modulename        = 'moodle';
            $eventdata->component         = 'enrol_ccavenue';
            $eventdata->name              = 'ccavenue_enrolment';
            $eventdata->userfrom          = $teacher;
            $eventdata->userto            = $user;
            $eventdata->subject           = get_string("enrolmentnew", 'enrol', $shortname);
            $eventdata->fullmessage       = get_string('welcometocoursetext', '', $a);
            $eventdata->fullmessageformat = FORMAT_PLAIN;
            $eventdata->fullmessagehtml   = '';
            $eventdata->smallmessage      = '';
        }

        if ((!empty($mailteachers)) and $teacher) {
 
            $a->course 	= format_string($course->fullname, true, array('context' => $coursecontext));
            $a->user 	= fullname($user);
			$eventdata 	= new stdClass();
			
            $eventdata->modulename        = 'moodle';
            $eventdata->component         = 'enrol_ccavenue';
            $eventdata->name              = 'ccavenue_enrolment';
            $eventdata->userfrom          = $user;
            $eventdata->userto            = $teacher;
            $eventdata->subject           = get_string("enrolmentnew", 'enrol', $shortname);
            $eventdata->fullmessage       = get_string('enrolmentnewuser', 'enrol', $a);
            $eventdata->fullmessageformat = FORMAT_PLAIN;
            $eventdata->fullmessagehtml   = '';
            $eventdata->smallmessage      = '';
            message_send($eventdata);
        }

       if (!empty($mailadmins)) {
	   
	  
			$a->course 	= format_string($course->fullname, true, array('context' => $coursecontext));
            $a->user 	= fullname($user);
		    $admins 	= get_admins();
			$message 	= get_string('enrolmentnewuser', 'enrol', $a)."\n<br>";
		 
			$skip_key =array('merchant_param1', 'merchant_param2', 'courseid', 'instanceid', 'userid', 'timeupdated','merchant_id');
			foreach ($data as $key => $value) {
				if (in_array($key, $skip_key)) continue;
				$message .= ucwords(str_replace("_"," ",$key))  ."\t\t\t:\t\t\t". $value."\n<br>";
			}
            foreach ($admins as $admin) {
			 
                $eventdata = new stdClass();
                $eventdata->modulename        = 'moodle';
                $eventdata->component         = 'enrol_ccavenue';
                $eventdata->name              = 'ccavenue_enrolment';
                $eventdata->userfrom          = $user;
                $eventdata->userto            = $admin;
                $eventdata->subject           = get_string("enrolmentnew", 'enrol', $shortname);
                $eventdata->fullmessageformat = FORMAT_HTML;
                $eventdata->fullmessagehtml   = $message;
                $eventdata->smallmessage      = '';
                message_send($eventdata);	
				
				//CC to user also 
				$eventdata = new stdClass();
                $eventdata->modulename        = 'moodle';
                $eventdata->component         = 'enrol_ccavenue';
                $eventdata->name              = 'ccavenue_enrolment';
                $eventdata->userfrom          = $admin;
                $eventdata->userto            = $user;
                $eventdata->subject           = get_string("enrolmentnew", 'enrol', $shortname);
                $eventdata->fullmessageformat = FORMAT_HTML;
                $eventdata->fullmessagehtml   = $message;
                $eventdata->smallmessage      = '';
                message_send($eventdata);	
            }
        }
		 
}

elseif ($data->payment_status != "Success" and $data->payment_status != "Pending") 
{
	if(count($response_array)>0)
	{
		$plugin->unenrol_user($plugin_instance, $data->userid);
		$payment_message_array[] = message_ccavenue_error_to_admin("Payment Status not completed or pending. User unenrolled from course", $data);		
	}
}
	
if (is_enrolled($context, NULL, '', true)) 
{ 
	// TODO: use real ccavenue check
	redirect($destination, get_string('paymentthanks', '', $fullname));
} 
else
{  
	// Somehow they aren't enrolled yet!  :-(
	$PAGE->set_context(get_system_context());
	$PAGE->set_url($destination);		
	echo $OUTPUT->header();
	$a = new stdClass();
	$a->teacher 	 = get_string('defaultcourseteacher');
	$a->fullname	 = $fullname;
	$payment_message = implode("<br />",$payment_message_array)."<br />";
	notice($payment_message. get_string('paymentsorry', '', $a), $destination);
}


//--- HELPER FUNCTIONS --------------------------------------------------------------------------------------

function message_ccavenue_error_to_admin($subject, $data) {    
    $admin 	 = get_admin();
    $site 	 = get_site();
    $message = "$site->fullname:  Transaction failed.\n\n$subject\n\n";
    foreach ($data as $key => $value) {
        $message .= ucwords(str_replace("_"," ",$key))  ."\t\t\t:". $value;
    }
    $eventdata = new stdClass();
    $eventdata->modulename        = 'moodle';
    $eventdata->component         = 'enrol_ccavenue';
    $eventdata->name              = 'ccavenue_enrolment';
    $eventdata->userfrom          = $admin;
    $eventdata->userto            = $admin;
    $eventdata->subject           = "ccavenue ERROR: ".$subject;
    $eventdata->fullmessage       = $message;
    $eventdata->fullmessageformat = FORMAT_PLAIN;
    $eventdata->fullmessagehtml   = '';
    $eventdata->smallmessage      = '';
    //message_send($eventdata);
    return  $subject;
}
