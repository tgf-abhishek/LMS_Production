<?php
// --------------------------------------------------------- 
// block_cmanager is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// block_cmanager is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
//
// COURSE REQUEST MANAGER BLOCK FOR MOODLE
// by Kyle Goslin & Daniel McSweeney
// Copyright 2012-2018 - Institute of Technology Blanchardstown.
// --------------------------------------------------------- 
/**
 * COURSE REQUEST MANAGER
  *
 * @package    block_cmanager
 * @copyright  2018 Kyle Goslin, Daniel McSweeney
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once("../../config.php");
global $CFG, $DB;
$formPath = "$CFG->libdir/formslib.php";
require_once($formPath);
require_login();
require_once('../../course/lib.php');
require_once('lib/displayLists.php');
require_once('lib/boot.php');

/** Navigation Bar **/
$PAGE->navbar->ignore_active();
$PAGE->navbar->add(get_string('cmanagerDisplay', 'block_cmanager'), new moodle_url('/blocks/cmanager/cmanager_admin.php'));

$PAGE->set_url('/blocks/cmanager/cmanager_admin.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_heading(get_string('pluginname', 'block_cmanager'));
$PAGE->set_title(get_string('pluginname', 'block_cmanager'));
echo $OUTPUT->header();


$_SESSION['CRMisAdmin'] = true;


$context = context_system::instance();
if (has_capability('block/cmanager:approverecord',$context)) {
} else {
  print_error(get_string('cannotviewconfig', 'block_cmanager'));
}



?>


<link rel="stylesheet" type="text/css" href="css/main.css" />
<link rel="stylesheet" type="text/css" href="js/jquery/jquery-ui.css"></script>
<script src="js/jquery/jquery-3.3.1.min.js"></script>
<script src="js/jquery/jquery-ui.js"></script>

<script src="js/bootstrap.min.js"/>


  
<style>
#map { float:left; width:80%; }
#wrapper {float:left; width:100%;}
#list { background:#eee; list-style:none; padding:0; }
#existingrequest { background:#000; }


select
{
    width:150px;
}
tr:nth-child(odd)		{ background-color:#eee; }
tr:nth-child(even)		{ background-color:#fff; }

</style>



<?php


/**
 * Admin console
 *
 * Admin console interface
 * @package    block_cmanager
 * @copyright  2018 Kyle Goslin, Daniel McSweeney
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_cmanager_admin_form extends moodleform {

function definition() {
    global $CFG;
    global $USER, $DB;
    $mform =& $this->_form; // Don't forget the underscore!


    $selectQuery = "status = 'PENDING' ORDER BY id ASC";

    // If search is enabled then use the
    // search parameters
    if ($_POST && isset($_POST['search'])) {
        
       
        $searchText = required_param('searchtext', PARAM_TEXT);
        // if nothing was entered for the search string
        // send them back with a warning message.
        if($searchText == ""){
            echo generateGenericPop('genpop1',get_string('alert','block_cmanager'),get_string('cmanager_admin_enterstring','block_cmanager'),get_string('ok','block_cmanager'));
            echo '<script>$("#genpop1").modal(); 
            
            $("#genpop1").click(function(){
              
             window.location="cmanager_admin.php";
            });
            
            </script>';
            
            die;
        }
        $searchType = required_param('searchtype', PARAM_TEXT);

        if (!empty($searchText) && !empty($searchType)) {
            if ($searchType == 'code') {
                $selectQuery = "`modcode` LIKE '%{$searchText}%'";
            }
            else if($searchType == 'title') {
	        $selectQuery = "`modname` LIKE '%{$searchText}%'";
        }
       else if ($searchType == 'requester') {
	        $selectQuery = "`createdbyid` IN (Select id from ".$CFG->prefix.
            "user where `firstname` LIKE '%{$searchText}%' OR `lastname` 
            LIKE '%{$searchText}%' OR `username` LIKE '%{$searchText}%')";
       }
   }

  //  if ($searchType != 'requester'){
        $selectQuery .= " AND status = 'PENDING' ORDER BY id ASC";
  //  }
}


// Get the list of records
$pendingList = $DB->get_recordset_select('block_cmanager_records', $select=$selectQuery);
$outputHTML = block_cmanager_display_admin_list($pendingList, true, true, true, 'admin_queue');

$mform->addElement('header', 'mainheader', '<span style="font-size:18px">'.get_string('currentrequests','block_cmanager').'</span>');


$bulkActions = "<p></p>
			    <div style=\"width: 210px; text-align:left; padding:10px; font-size:11pt; background-color: #eee\">

                <b>".get_string('bulkactions','block_cmanager')."</b>
			<br>
			<input type=\"checkbox\" onClick=\"toggle(this)\" /> Select All<br/>

			<select id=\"bulk\" onchange='bulkaction();'>
			  <option></option>
			  <option value =\"Approve\"'>".get_string('bulkapprove','block_cmanager')."</option>
			  <option value=\"Deny\">".get_string('deny','block_cmanager')."</option>
			  <option value =\"Delete\"'>".get_string('delete','block_cmanager')."</option>
			</select>
			<p></p>
			</div>";



$page1_fieldname1 = $DB->get_field_select('block_cmanager_config', 'value', "varname='page1_fieldname1'");
$page1_fieldname2 = $DB->get_field_select('block_cmanager_config', 'value', "varname='page1_fieldname2'");

$searchHTML = '

        <div style="width: 210px; background-color:#eee; padding:10px; ">
	 	<form action="cmanager_admin.php?search=1" method="post">

	 	<b><span style="font-size:11pt">'.get_string('search_side_text', 'block_cmanager').'</span></b>
	 	<br> <input type="text" name="searchtext" id="searchtext"></input><br>
	 	<span style="font-size:11pt">
	 	<select name="searchtype" id="searchtype">
  		<option value="code">'.$page1_fieldname1.'</option>
		<option value="title">'.$page1_fieldname2.'</option>
  		<option value="requester">' . get_string('searchAuthor', 'block_cmanager').'</option>
		</select>
		</span>
		<br>
		<span style="font-size:11pt">
		<input type="submit" value="'.get_string('searchbuttontext', 'block_cmanager').'" name="search"></input>
		</span>
		</form>

		';

		if ($_POST && isset($_POST['search'])) {
			$searchHTML .= '<br><p></p><a href="cmanager_admin.php">['.get_string('clearsearch', 'block_cmanager').']</a>';
		}
$searchHTML .= '</div>';




$mainBody ='

	<div id="pagemain">
		<div id="leftpanel" style="padding-right:10px; width:200px; float:left; height:100%">' .$searchHTML .''. $bulkActions . '</div>
			<div id="rightpanel" style=" margin-left:250px;">

					<div id="wrapper">'. $outputHTML .'</div>

		</div>
    </div>';


$mform->addElement('html', $mainBody);




    } // Close the function
}  // Close the class



echo "<script></script>";


$mform = new block_cmanager_admin_form();

if ($mform->is_cancelled()) {


} else if ($fromform=$mform->get_data()) {


} else {

}


$mform->focus();
$mform->display();
echo $OUTPUT->footer();



if ($_POST && isset($_POST['search'])) {
    $searchText = required_param('searchtext', PARAM_TEXT);
    $searchType = required_param('searchtype', PARAM_TEXT);

    echo "<script>document.getElementById('searchtext').value = '$searchText'; ";
    echo "
    var desiredValue = '$searchType';
    var el = document.getElementById('searchtype');
    for(var i=0; i<el.options.length; i++) {
      if ( el.options[i].value == desiredValue ) {
        el.selectedIndex = i;
        break;
      }
    }
    </script>
    ";

}

// Modal for deleting requests
echo generateGenericConfirm('delete_modal', get_string('alert', 'block_cmanager') , 
                                    get_string('configure_delete', 'block_cmanager'), 
                                    get_string('yesDeleteRecords', 'block_cmanager'));

// Modal for quick approve                                     
echo generateGenericConfirm('quick_approve', get_string('alert', 'block_cmanager') , 
                                     get_string('quickapprove_desc', 'block_cmanager'), 
                                     get_string('quickapprove', 'block_cmanager'));
?>




 
<script>
var deleteRec = 0;
var quickApp = 0;
// quick approve ok button click handler
$("#okquick_approve").click(function(){
   
   window.location = "admin/bulk_approve.php?mul=" + quickApp;
});



// delete request ok  button click handler
$("#okdelete_modal").click(function(){
   
   
   window.location = "deleterequest.php?t=a&&id=" + deleteRec;
});

  

// Ask the user do they really want to delete
// the request using a dialog.
function cancelConfirm(id,langString) {
	deleteRec = id;
    $("#popup_text").html(langString);
    $("#delete_modal").modal();
    
   


}

function quickApproveConfirm(id,langString) {
    quickApp = id;
    window.onbeforeunload = null;    
    $("#popup_quick_text").html(langString);
    $("#quick_approve").modal();
    

}


var checkedIds  = ['null'];


// List of currently selected Ids for use
// with the bulk actions
function addIdToList(id) {
	var i = checkedIds.length;
	var found = false;

	while (i--) {
        if (checkedIds[i] === id) {
	      	checkedIds[i] = 'null';
			found = true;
	    }
	}

    if (found === false) {
        checkedIds.push(id);
	}
}


/**
 * This function is used to save the text from the
 * categories when they are changed.
 */
function saveChangedCategory(recordId) {

   var fieldvalue = document.getElementById('menucat' + recordId).value;


    $.post("ajax_functions.php", { type: 'updatecategory', value: fieldvalue, recId: recordId },
    		   function(data) {
    		     //
    		   });


}


// When the select all option is picked in the bulk actions
// this is the function that is run.
function toggle(source) {
  checkboxes = document.getElementsByName('groupedcheck');
  for(var i=0, n=checkboxes.length;i<n;i++) {
    checkboxes[i].checked = source.checked;
        addIdToList(checkboxes[i].id);
  }
}


//
// List of the different bulk actions that can be performed
// on different requests in the queue.
//
function bulkaction(){

    var cur = document.getElementById('bulk');

    if(cur.value == 'Delete'){

	$.post("ajax_functions.php", { type: "del", values: checkedIds},
		   function(data) {
		    		window.location='cmanager_admin.php';

		   });

	}

	
	if(cur.value == 'Deny'){
		window.location='admin/bulk_deny.php?mul=' + checkedIds;
	}

	
	if(cur.value == 'Approve'){
		window.location='admin/bulk_approve.php?mul=' + checkedIds;
	}

}
</script>



 
