<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

global $wpdb;
require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
//create tables


$tbl_fields = 'wp_tr_lock_ip';
$sql_fields = "CREATE TABLE `{$tbl_fields}` (
  `ip` varchar(50) DEFAULT NULL,
  `loginfail` int(11) default 0,
  `cookiefail` int(11) default 0,
  `lasttime` bigint(15) default 0,
  `bantime` bigint(15) default 0,
  `unlock` VARCHAR (50) default '',
  PRIMARY KEY (`ip`)
) ENGINE=InnoDB";
$rs = @dbDelta($sql_fields);


$old_name = $wpdb->prefix . 'tr_security_log';
$new_name = 'wp_tr_security_log';
if($old_name != $new_name)
{
    @dbDelta("RENAME TABLE {$old_name} TO {$new_name}");
}

$sql_fields = "CREATE TABLE `{$new_name}` (
  `msg` text,
  `username` varchar(50),
  `ip` varchar(50),
  `ltype` varchar(10) not null,
  `ltime` int(11)
) ENGINE=InnoDB ;";
$rs = dbDelta($sql_fields);



$sql_fields = "CREATE TABLE `wp_tr_log` (
  `ip` varchar(50),
  `url` varchar(500),
  `log` text,
  `load_time` varchar(15),
  `load_start` datetime,
  `log_type` varchar(10) not null
) ENGINE=MyISAM ;";

$rs = dbDelta($sql_fields);


$sql = "CREATE TABLE `wp_tr_file_logs` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `file` varchar(250),
  `ftype` char(1),
  `lasttime` datetime,
  `changed` datetime,
  `sizechanged` int(8),
  `synced` int(1) default 0,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM";
dbDelta($sql);

if(get_option('tr_security')===false)
{   
    //default security
    $config = array(    
    	'option_group' => 'tr_security',   
        'path' => dirname(dirname(__FILE__)),
    ); 
    $secu = new TR_Admin_Page_Class_V9($config);
    $secu->loadconfig('trcs_security');
    $secu->restore();
    
}

if(get_option('trcs_cache')===false)
{
    //default cache
    $config = array(    
    	'option_group' => 'trcs_cache',   
        'path' => dirname(dirname(__FILE__)),
    );  
    $secu = new TR_Admin_Page_Class_V9($config);
    $secu->loadconfig('trcs_cache');
    $secu->restore();
}


wp_clear_scheduled_hook('cache_security');
wp_schedule_event(time()+300, 'hourly', 'cache_security');

if(!@opendir(TRSCSC_CACHE_PATH))
    wp_mkdir_p(TRSCSC_CACHE_PATH);

if(!file_exists(TRSCSC_CACHE_QUERIES))
{
    wp_mkdir_p(TRSCSC_CACHE_QUERIES);
}


include_once(TRSCSC_PATH.'inc/admin_actions.php');
//  tradmin_action_generate_config(false,false);



//add htaccess to uploads folder
$htcontent = file_get_contents(TRSCSC_PATH.'wp-content/.htaccess');


@file_put_contents(WP_CONTENT_DIR.'/uploads/.htaccess',$htcontent);


//check robots.txt
$robots_file = ABSPATH.'/robots.txt';
if(!file_exists($robots_file))
{
    $d = file_get_contents(TRSCSC_PATH.'wp-content/robots.txt');
    file_put_contents($robots_file,$d);
}

