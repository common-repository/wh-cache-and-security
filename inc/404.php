<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
//check security hide backend
$secu = get_option('tr_security');

if($secu['hide_backend'])
{
    
    $key = get_option('tr_security_admin_key');
    if(stripos($_SERVER['REQUEST_URI'],  '/'.$secu['login_slug'])!==false)
    {
        $loginurl = get_bloginfo('url').'/wp-login.php?'.$key;
        wp_redirect($loginurl);
        exit;
    }
}

//clear cache if removed css
if(stripos($_SERVER['REQUEST_URI'],'wp-content/cache/css/')!==false)
{
    $log = $_SERVER['REQUEST_URI']."\n".$_SERVER['HTTP_REFERER'];
    $ref = $_SERVER['HTTP_REFERER'];
    if(!empty($ref))
    {
        if(function_exists('w3_instance')){
            wp_redirect($ref.'?w3tc_note=flush_pgcache');
            exit;
        }else{
            $link = preg_replace('~^.*?://~', '', $ref);
            $file = md5($link);
            $filename = $file . '.dat';
            $mobilefile = $file . '_mobile.dat';
            @unlink(TRSCSC_CACHE_PATH . '/' . $filename);
            @unlink(TRSCSC_CACHE_PATH . '/' . $mobilefile);
            @unlink(TRSCSC_CACHE_PATH . '/' . $filename . 's');
            @unlink(TRSCSC_CACHE_PATH . '/' . $mobilefile . 's');
        }
    }

    $filename = WP_CONTENT_DIR .'/log_404_css.txt';
    $string = date("Y-m-d H:i:s").": \n";
    $string .= $log;
    $string .= "\n\n";
    $f = fopen($filename, 'a+');
    fwrite($f, $string);
    fclose($f);
}