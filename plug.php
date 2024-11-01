<?php
/**
Plugin Name: WH Cache and Security
Description: Security your website and  fastest WP Cache system
Version: 1.1.2
Author: WebHue
Author URI: http://webhue.net
License: GPL2
*/

define('TRSCSC_FILE', __FILE__);
define('TRSCSC_URL', plugins_url('/',__FILE__));
define('TRSCSC_PATH',plugin_dir_path(__FILE__));
define('TRSCSC_CACHE_PATH',WP_CONTENT_DIR . '/cache/tr-cache');
define('TRSCSC_CACHE_JS','/cache/js');
define('TRSCSC_CACHE_CSS','/cache/css');
define('TRSCSC_CACHE_QUERIES',WP_CONTENT_DIR.'/cache/queries');
define('TRSCSC_SERVER','http://api.webhue.net');
define('TRSCSC_CM_CACHE',TRSCSC_PATH.'cm-cache.php');

if(!WP_DEBUG)
{
    error_reporting( E_CORE_ERROR | E_COMPILE_ERROR | E_ERROR | E_PARSE | E_USER_ERROR  | E_RECOVERABLE_ERROR );
}

include_once(TRSCSC_PATH.'admin/init.php');
include_once(TRSCSC_PATH.'inc/init.php');

