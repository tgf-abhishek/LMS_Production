<?php
require_once('../config.php');

global $DB;
global $USER;
//echo $USER->email;
$user = $DB->get_records('user', ['email' =>$USER->email]);                                                            
//print_r($user);
// Here you can access to every object value in the way that you want
   
   // echo  $obj->firstname." ".$obj->lastname;
foreach ($user as $obj)
{?>
   
    <input type="checkbox" id="users" name="usernames" value="username">
    <label for="usernames"><?php echo $obj->firstname." ".$obj->lastname; ?></label><br>
      
<?php   
}
?>

 


