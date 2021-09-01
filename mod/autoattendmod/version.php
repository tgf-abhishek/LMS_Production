<?php 

/////////////////////////////////////////////////////////////////////////////////
///  Code fragment to define the version of autoattendmod
///  This fragment is called by moodle_needs_upgrading() and /admin/index.php
/////////////////////////////////////////////////////////////////////////////////

defined('MOODLE_INTERNAL') || die();

//
$plugin->requires  = 2012120300;          // Moodle 2.4
$plugin->component = 'mod_autoattendmod';
$plugin->cron      = 300;
$plugin->maturity  = MATURITY_STABLE;

$plugin->release   = '2.6.0';

$plugin->version   = 2019082105;    // v2.6.0 for moodle-3.7.x
//$plugin->version = 2019081700;    // old excel version
//$plugin->version = 2016011200;    // backup
//$plugin->version = 2016031900;    // email function

