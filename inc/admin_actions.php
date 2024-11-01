<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

include_once(TRSCSC_PATH . 'inc/actions.php');

function tradmin_action_need_fix_plugin() {
    include_once(TRSCSC_PATH.'inc/need_fix_plugin.php');
    exit;
}

function tradmin_action_removeban() {
    global $tr_security,$wpdb;
    $ip = $_POST['ip'];
    $tr_security->updatebantime(0,$ip);
    $wpdb->delete('wp_tr_lock_ip',array('ip'=>$ip));
    trfront_action_change_htaccess();
    echo 'ok';exit;
}

function tradmin_action_clear_cache($path = '') {
    if(empty($path))
    {
        $path = TRSCSC_CACHE_PATH;
        tr_cache_clear_by_dir(WP_CONTENT_DIR.TRSCSC_CACHE_JS,0,time() + 999999);
    }
    if(stripos($path,'cache')===false)
    {
        return;
    }
    tr_cache_clear_by_dir($path,0,time() + 999999);
    echo 'ok';
    exit();
}

function tradmin_action_generate_config($uninstall = false, $change_ad_c = true) {
    return trfront_action_generate_config($uninstall, $change_ad_c);
}

function tradmin_action_change_config_file($onlycontent = false, $uninstall = false) {
    return trfront_action_change_config_file($onlycontent, $uninstall);
}

function tradmin_action_change_htaccess($onlycontent = false, $uninstall = false) {
    return trfront_action_change_htaccess($onlycontent, $uninstall);
}

function tradmin_action_change_advanced_cache($onlycontent = false, $uninstall = false) {
    return trfront_action_change_advanced_cache($onlycontent, $uninstall);
}
function tradmin_action_scan_quick_sc() {
    include_once(TRSCSC_PATH . 'pages/admin_scan_page.php');
}

function tradmin_action_do_scan_quick_sc() {
    if(!session_id())session_start ();
    $return = array('status'=>'done');
    @set_time_limit(60 * 5);
    include_once(TRSCSC_PATH . 'inc/tr_scan_dir.php');

    $dir = dirname(WP_CONTENT_DIR);
    $options = array(
        'skip_ext' => tr_get_option('tr_security', 'skip')
    );
    $scan = new Tr_Scan_Dir();
    $scan->scan($dir,$options);
    
    $log = '';
    $threats = $scan->get_log();
    foreach($threats as $file => $name)
    {
        $log.='<li>'.$file.': '.$name.'</li>';
    }
    if(count($threats)==0)
    {
        $log.='<h4 class="center">No found files</h4>';
    }
    $return['log']= $log;
    wp_send_json($return);
}

function tradmin_action_test_scan_dir()
{
    echo 'start...';
    include_once(TRSCSC_PATH . 'inc/tr_scan_dir.php');
    $start_time = microtime(true);
    $scan = new Tr_Scan_Dir();
    $scan->scan_log_time();
    echo microtime(true) - $start_time;echo '<br>';
    echo 'count: '.count($scan->files);
    echo 'ok';
    exit;
}

function tradmin_action_test_sync_log_file_time()
{
    $scan = new Tr_Scan_Dir();
    $rs = $scan->sync_server();
    var_dump($rs);
    echo 'ok';exit;
}

function tradmin_action_ci_run_optimize_images()
{
    $return = array('status'=>'ok','msg'=>'ok','status_msg'=>'','position'=>1);
    $op = new Tr_Optimize_Image();
    $op->set_size($_REQUEST['max_width'],$_REQUEST['max_height'],$_REQUEST['max_size']);
    $key = 'uploads_images';
    if($_REQUEST['position']==0)
    {
        $op->make_list(WP_CONTENT_DIR.'/cache/',$key);
    }else{
        $op->loaddata($key);
    }

    if($op->total > 0 && $op->total - $_REQUEST['position'] > 0)
    {
        $rs = $op->optimize($_REQUEST['position']);
        $return['msg'] = '<a href="'.$rs['url'].'" target="_blank">'.$rs['file'].'</a>'.' => '.(($rs['status'])? 'ok':'error');
    }else{
        $return['status']='done';
    }
    $return['status_msg'] = 'Optimized: '.$_REQUEST['position'].' / total: '.$op->total;
    $return['position'] = $_REQUEST['position']+1;
    
    wp_send_json($return);exit;
}