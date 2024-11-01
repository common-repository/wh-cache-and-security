<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if (!function_exists('tr_cache_callback')) {
    define('CACHE_SID', true);
    global $tr_cache_path, $tr_cache_options;
    $tr_cache_path = TRSCSC_CACHE_PATH.'/';
    $tr_cache_options = get_option('trcs_cache', array());

    include_once (TRSCSC_PATH . 'cache.php');
}