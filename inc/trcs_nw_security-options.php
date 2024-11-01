<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

global $wpdb,$tr_security;

wp_enqueue_script('cache-admin',TRSCSC_URL.'js/admin.js',array('jquery'),true);
add_action('admin_saved_option_trcs_nw_security','admin_saved_option_trcs_nw_security');

function admin_saved_option_trcs_nw_security($options_panel)
{
    global $wpdb;

    $options = get_option($options_panel->option_group);
    $options['white_ips_array'] = trcsc_validate_list_ip($options['white_ips']);

    $ips = implode("','",$options['white_ips_array']);
    $wpdb->query("delete from wp_tr_lock_ip where ip in ('{$ips}')");

    update_option($options_panel->option_group,$options);
}

function trcsc_validate_list_ip($ips)
{
    if(!is_array($ips))
    {
        $ips = explode("\n",$ips);
    }
    $return = array();

    foreach($ips as $ip)
    {
        $ip = trim($ip);
        $count = count(explode('.',$ip));

        if(empty($ip) || $count != 4)continue;
        $return[] = $ip;
    }
    return $return;
}


////----opens tabs
$options_panel->OpenTabs_container();
$options_panel->TabsListing(array(
    'links' => array(
        'status_tab' => __('Status'),
       // 'scan_tab' => __('Scan'),
        'ban_tab' => __('Ban'),
       // 'login_tab' => __('Login'),
       // 'hide_backend_tab' => __('Hide Backend'),
        //'ssl_tab' => __('SSL'),
        //'captcha_tab' => __('Captcha'),
        'logs_tab' => __('Logs'),
    )
));

$options_panel->OpenTab('status_tab');
$options_panel->Title("Status");
$options_panel->addCustom('status',array('name'=>''));
$options_panel->CloseTab();


$options_panel->OpenTab('ban_tab');
$options_panel->Title("Ban");
$mypip = $tr_security->get_ip();
$options_panel->addCustom('autoban',array('name'=>'Auto Ban IP (IPs auto add if that is server try to login, DDos...)'));
$options_panel->addTextarea('white_ips',array('name'=>'White IPs','std'=>'',
    'desc'=>'Add my IP: <a onclick="jQuery(\'#white_ips\').val(jQuery(\'#white_ips\').val()+\'\n'.$mypip.'\')">'.$mypip.'</a>',
    'width'=>'90%'));


$options_panel->CloseTab();






$options_panel->OpenTab('logs_tab');
$options_panel->addCheckbox('log_time',array('name'=>'Log Time','std'=>false,));
$options_panel->Title("Logs");
$options_panel->addCustom('logs',array('name'=>''));
$options_panel->CloseTab();



