<?php


defined('MOODLE_INTERNAL') || die();
global $PAGE;
$PAGE->requires->jquery();

if (defined('DOM_BZ_PATH_PG_MAIN_201')== false) 
{
	define("DOM_BZ_PATH_PG_MAIN_201",$CFG->dirroot.'/enrol/ccavenue/common/');
}
$file_bz_dom = DOM_BZ_PATH_PG_MAIN_201."cbdom_main.php"; 
$file_bz_setting_function = $CFG->dirroot.'/enrol/ccavenue/setting_functions.php'; 

if (file_exists($file_bz_dom)) {
	include_once($file_bz_dom);
} 

if (file_exists($file_bz_setting_function)) {
	include_once($file_bz_setting_function);
}
 
$_pgmod_ver			= "3.4";				//==> Module Version same as in api
$_pgcat				= "CCAvenue";			//==>Category same as in api
$_pgcat_ver  		= "MCPG-3.4";			//==>Category Version same as in api
$_pgcms 			= "Moodle";				//==>CMS same as in api
$_pgcms_ver 		= "3.3";				//==>CMS Version same as in api
$_pg_lic_key 		= 'FREE';				//Payment module license key same as in api

if(!defined('DOM_BZ_CC_PGMOD_VER'))	define("DOM_BZ_CC_PGMOD_VER",$_pgmod_ver);
if(!defined('BZCCPG_MOD_VERSION'))	define("BZCCPG_MOD_VERSION",$_pgmod_ver);
if(!defined('BZCCPG_CMS'))	define("BZCCPG_CMS",$_pgcms);
if(!defined('DOM_BZ_CC_PGCAT'))		define("DOM_BZ_CC_PGCAT",$_pgcat);
if(!defined('DOM_BZ_CC_PGCAT_VER'))	define("DOM_BZ_CC_PGCAT_VER",$_pgcat_ver);
if(!defined('DOM_BZ_CC_PGCMS'))		define("DOM_BZ_CC_PGCMS",$_pgcms);
if(!defined('DOM_BZ_CC_PGCMS_VER'))	define("DOM_BZ_CC_PGCMS_VER",$_pgcms_ver);
if(!defined('DOM_BZ_CC_PG_LIC_KEY'))define("DOM_BZ_CC_PG_LIC_KEY",$_pg_lic_key);

$PAGE->requires->js(new moodle_url($CFG->wwwroot . '/enrol/ccavenue/js/ccavenue.js'));


if ($ADMIN->fulltree) {

	if (!during_initial_install()) {
		$token						= 'Moodle';
		$ccavenuepay_license_key 	= DOM_BZ_CC_PG_LIC_KEY;	
		loadDefaults($token,$ccavenuepay_license_key);		
	}
	
	if (!class_exists('cbdom_main')) {
		$settings->add(new admin_setting_heading('enrol_ccavenue_settings', '',get_string('ccavenue_error', 'enrol_ccavenue')));
		return false;
	}
	else{	
		
		$log_image = '<img  src= "https://www.ccavenue.com/images_shoppingcart/ccavenue_pay_options.gif" />';
		$lic_key_left_panel = '<fieldset class="form-wrapper" id="edit-ccavenue-main-panel" style="border:1px solid #ccc; margin-bottom:15px;">
									<div class="fieldset-wrapper" >

										<div id="ccavenue-main-panel-left" style="float:left; padding:  23px 5px 0px; border-right: 1px dashed #cccccc; height: 105px">
											<a href="https://www.ccavenue.com" target="_blank">
												<img typeof="foaf:Image" src="https://www.ccavenue.com/images_shoppingcart/ccavenue_logo_india.png" alt="ccavenues Logo">
											</a>

											<div>

												<b>	<font color="sky blue"> </font> 
													<font color="red"> </font>
													<font color="sky blue"></font>
												</b>

												<span id="ccavenue_module_lic_key" style="display:none">FREE</span>
												<span id="ccavenue_module_ver" style="display:none">'.BZCCPG_MOD_VERSION.'</span>
												<span id="ccavenue_module_name" style="display:none">'.BZCCPG_CMS.'</span>
											</div>

											</div>

											<div id="ccavenue-main-panel-midd" style="display: inline-block; margin-left: 20px;  float:left">
												<h3 class="panel-title">CCAvenue MCPG </h3>
												
												<a style="text-decoration: none; font-size:16px;font-family:Verdana, Geneva, sans-serif; color:#09F;">Module Version:
												</a>

												<a style="text-decoration: none;color:#390; font-family:Verdana, Geneva, sans-serif; font-size:12px; font-weight:bold">'.BZCCPG_MOD_VERSION.'
												</a><br>
 <a style="text-decoration: none;color:#390; font-family:Verdana, Geneva, sans-serif; font-size:12px; font-weight:bold" href="mailto:shoppingcart@ccavenue.com?subject=India%20Shopping%20Cart%20-%20Moodle%20'.BZCCPG_MOD_VERSION.'">Contact Support</a>			  
											
											</div>

									</div>
								</fieldset>';						
		$lic_panel 		= $lic_key_left_panel;
		$develop_label	= '';
		 $settings->add($log_image);

		$settings->add(new admin_setting_heading('enrol_ccavenue_settings', '',get_string('pluginname_desc', 'enrol_ccavenue').$lic_panel));
		$settings->add(new admin_setting_configtext('enrol_ccavenue/ccavenuemerchantid', get_string('merchant_id', 'enrol_ccavenue'), get_string('merchantId_desc', 'enrol_ccavenue'), '', PARAM_TEXT  ));
		$settings->add(new admin_setting_configtext('enrol_ccavenue/ccavenueworkingkey', get_string('workingkey', 'enrol_ccavenue'), get_string('workingkey_desc', 'enrol_ccavenue'), '', PARAM_TEXT  ));
		$settings->add(new admin_setting_configtext('enrol_ccavenue/ccavenueaccesscode', get_string('accesscode', 'enrol_ccavenue'), get_string('accesscode_desc', 'enrol_ccavenue'), '', PARAM_TEXT  ));
		$settings->add(new admin_setting_configcheckbox('enrol_ccavenue/mailstudents', get_string('mailstudents', 'enrol_ccavenue'), '', 0));
		$settings->add(new admin_setting_configcheckbox('enrol_ccavenue/mailteachers', get_string('mailteachers', 'enrol_ccavenue'), '', 0));
		$settings->add(new admin_setting_configcheckbox('enrol_ccavenue/mailadmins', get_string('mailadmins', 'enrol_ccavenue'), '', 0));

		$settings->add(new admin_setting_heading('enrol_ccavenue_defaults',
			get_string('enrolinstancedefaults', 'admin'), get_string('enrolinstancedefaults_desc', 'admin')));

		$options = array(ENROL_INSTANCE_ENABLED  => get_string('yes'),
						 ENROL_INSTANCE_DISABLED => get_string('no'));
		$settings->add(new admin_setting_configselect('enrol_ccavenue/status',
			get_string('status', 'enrol_ccavenue'), get_string('status_desc', 'enrol_ccavenue'), ENROL_INSTANCE_DISABLED, $options));

		$settings->add(new admin_setting_configtext('enrol_ccavenue/cost', get_string('cost', 'enrol_ccavenue'), '', 0, PARAM_FLOAT, 4));

								 
		$ccavenuecurrencies =  enrol_get_plugin('ccavenue')->get_currencies();
		$settings->add(new admin_setting_configselect('enrol_ccavenue/currency', get_string('currency', 'enrol_ccavenue'), '', 'USD', $ccavenuecurrencies));
	   
	   if (!during_initial_install()) {
	   
			$options = get_default_enrol_roles(get_context_instance(CONTEXT_SYSTEM));
			$student = get_archetype_roles('student');
			$student = reset($student);
			$settings->add(new admin_setting_configselect('enrol_ccavenue/roleid',
				get_string('defaultrole', 'enrol_ccavenue'), get_string('defaultrole_desc', 'enrol_ccavenue'), $student->id, $options));
		}
		$settings->add(new admin_setting_configtext('enrol_ccavenue/enrolperiod',
			get_string('enrolperiod', 'enrol_ccavenue'), get_string('enrolperiod_desc', 'enrol_ccavenue'), 0, PARAM_INT));
	}
}

