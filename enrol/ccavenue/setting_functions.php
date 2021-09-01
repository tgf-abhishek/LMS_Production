<?php

function loadDefaults($token,$ccavenuepay_license_key)
{
	if (!class_exists('Cbdom_main')) {
		return ;
	}
	$cbdom = new Cbdom_main();

	$checked  		= "";
	if(!empty($ccavenuepay_license_key)){                                          
		$getres 		= json_decode($checked,true);
		if(isset($getres['success']))
		{

			if(!defined('CCAVENUE_PG_MODULE_ALERT_MESSAGE') &&  isset($getres['module_alert_message']))
			{
				define('CCAVENUE_PG_MODULE_ALERT_MESSAGE',$getres['module_alert_message']) ;
			}			 
		}
		$successtxt	= 'Success: You have modified CCAvenue MCPG account details!';

		if(!is_array($getres) || array_key_exists('error',$getres)){
			$errortxt = "Not installed!!! Error:".$getres['error'];
		}
		else{
			$lic_key_success = DOM_BZ_CC_PG_LIC_KEY;
			installbzCc(DOM_BZ_CC_PG_LIC_KEY);			
		}
		$lic_key_success = DOM_BZ_CC_PG_LIC_KEY;
		
		if(isset($_POST['ajax']) && $_POST['ajax'] == 'true'){
			echo json_encode(array('error'=>$errortxt,'success'=>$successtxt));
			exit;					
		}		
	} 	
	else 
	{
		$license_key = $ccavenuepay_license_key;
		if(empty($license_key)){
			echo 'You need to set license key for complete installation!!';
		}
		if(!empty($settings['ccavenuepay_license_key'])){
			$ccavenuepay_license_key = lic_key_success;
		}
		$ccavenuepay_license_key =  $ccavenuepay_license_key;
	}
	unset($cbdom);
}


?>
