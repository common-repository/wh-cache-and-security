<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Tr_Log_Time{

    public function __construct()
    {
        add_action('shutdown',array(&$this,'shutdown'));
    }

    public function shutdown()
    {
        global $timestart,$wpdb,$tr_security;

        $total_time = microtime(true) - $timestart;
        $log = '';
        if(count($_POST)>0)
        {
            $log = json_encode($_POST);
        }
        $data = array(
            'ip' => $tr_security->get_ip(),
            'url' => $_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'],
            'load_time' => $total_time,
            'load_start' => date('Y-m-d H:i:s',$timestart),
            'log_type' => 'TIME',
            'log' => $log,
        );
        $wpdb->insert('wp_tr_log',$data);
    }


}