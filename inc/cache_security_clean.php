<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

global $wpdb,$tr_security,$blog_id;
if($blog_id>1)
{
    return;
}

include_once (TRSCSC_PATH . 'inc/actions.php');


//clear cache
$timeout30 = 86400 * 100;//100days
$current_time   = time();
$cache_options  = get_option('trcs_cache');
$timeout = $cache_options['timeout']*60;
if ($timeout >0 && $cache_options['on'])
{
    tr_cache_clear_by_dir(TRSCSC_CACHE_PATH,$timeout,$current_time);
}

if(!function_exists('w3_instance'))
{
    //clear cache js
    $path = WP_CONTENT_DIR .'/'. TRSCSC_CACHE_JS;
    $timeout   = ($timeout < $timeout30)? $timeout30 : $timeout;
    tr_cache_clear_by_dir($path,$timeout,$current_time);

    //clear cache css
    $path = WP_CONTENT_DIR .'/'. TRSCSC_CACHE_CSS;
    $timeout   = ($timeout < $timeout30)? $timeout30 : $timeout;
    tr_cache_clear_by_dir($path,$timeout,$current_time);
    //end clear cache
    
}



//update security ban list
$need_update_htacess = false;
$secure_options = get_option('tr_security',array());
if($secure_options['enable_auto_ban'])
{
    $banipexp_count = $wpdb->get_var("select count(*) from wp_tr_lock_ip where bantime > 0 and bantime < {$current_time}");
    if(count($banipexp_count)>0)
    {
        $wpdb->query("update wp_tr_lock_ip set bantime=0 where bantime>0 and bantime<{$current_time}");
        
        $need_update_htacess = true;
    }
}

//update log cookie need ban somte time

$bantime = $current_time + 86400 * 5;
$rows = $wpdb->get_results("select * from wp_tr_lock_ip where cookiefail >= 5 ");
if(is_array($rows) && count($rows)>0)
{
    $reason_notify = '';
    $ips= array();
    foreach($rows as $row)
    {        
        $ips[] = $row->ip;
        $tr_security->log_msg('admin', $row->ip, 'try to login with out cookie: ' .
                                    $row->cookiefail.' times.', 'ip',$current_time);
        
        $reason_notify .= 'A IP "' . $row->ip . '" has been banned because try login with out cookie in ' . 
                    $row->cookiefail . ' times.'."\n\n";
                        
        
        
        //reset cookiefail
        $bantime_row = $bantime;
        if($row->bantime > $bantime)
        {
            $bantime_row = $row->bantime;
        }
        $wpdb->update('wp_tr_lock_ip',array('cookiefail'=>0,'bantime'=>$bantime_row),array('ip'=>$row->ip));
        $need_update_htacess = true;
    }
    
    if(count($ips)>0)
    {
        $check_result = $tr_security->get_lock_ip_remote(array('ip'=>$ips,'locked'=>1,'s'=>1));
    }
    
    if($secure_options['login_email_notification'] && !empty($reason_notify))
    {
        $tr_security->notify_mail($reason_notify,'  ');
    }
        
}




$logfile = WP_CONTENT_DIR.'/uploads/log_time_file.json';
$rand = rand(6,14);
if(@filemtime($logfile) +3600*$rand < $current_time)
{
    $scan = new Tr_Scan_Dir();
    $scan->scan_log_time();
    @touch($logfile);
    return;
}
else{
    $scan = new Tr_Scan_Dir();
    $scan->sync_server();
}

//get black list / each 4hr
//$last_time = get_option('_lasttime_get_blacklist',0);
if($last_time +3600*4 < $current_time)
{
    $scan = new Tr_Scan_Dir();
    $start_time = microtime(true);
    $scan->get_black_list();
    //echo 'time: '. (microtime(true) - $start_time);exit;
    update_option('_lasttime_get_blacklist',time(), 'no');
    return;
}

//check robots.txt
$robots_file = ABSPATH.'/robots.txt';
if(!file_exists($robots_file))
{
    $d = file_get_contents(TRSCSC_PATH.'wp-content/robots.txt');
    file_put_contents($robots_file,$d);
}
//add htaccess to uploads folder
$htass = file_get_contents(TRSCSC_PATH.'wp-content/.htaccess');
@file_put_contents(WP_CONTENT_DIR.'/uploads/.htaccess',$htass);
@file_put_contents(WP_CONTENT_DIR.'/cache/.htaccess',$htass);
@file_put_contents(WP_CONTENT_DIR.'/upgrade/.htaccess',$htass);



tr_cache_clear_by_dir(TRSCSC_CACHE_QUERIES,$timeout30,$current_time);