<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Tr_Log_Net
{
    var $urls;
    var $logfile;
    var $current;


    function __construct() {
        $this->logfile = is_admin()? 'admin_log.txt':'front_log.txt';
        add_filter('http_request_args',array(&$this,'http_request_args'),9,2);
        add_filter('pre_http_request',array(&$this,'pre_http_request'),999,3);
        add_filter('http_response',array(&$this,'http_response'),99);
    }

    function __destruct() {
        if(count($this->urls)==0)return;
        $filename = WP_CONTENT_DIR .'/'.$this->logfile;
        $string = date("Y-m-d H:i:s").": \n";
        $string .= implode("\n",$this->urls);
        $string .= "\n\n";
        $f = fopen($filename, 'a+');
        fwrite($f, $string);
        fclose($f);
        if(is_admin())return;
        //log ips
        $ip = $_SERVER['REMOTE_ADDR'];
        $filename = WP_CONTENT_DIR .'/ips.txt';
        $f = fopen($filename, 'a+');
        $ip = date("Y-m-d H:i:s").": ".$ip.'=>'.$_SERVER['REQUEST_URI'].'=>'.$_SERVER['HTTP_USER_AGENT']."\n";
        fwrite($f, $ip);
        fclose($f);
    }

    public  function http_response($response, $args='', $url ='')
    {
        $args_str = '';
        ob_start();
        var_export($response);
        $args_str = ob_get_clean();
        $url .= ': '.$args_str."\n";
        $this->urls[] = $url;

        return $response;
    }

    public function http_request_args($args,$url)
    {
        $args_str = '';
        foreach($args as $k => $vl)
        {
            ob_start();
            var_export($vl);
            $vl = ob_get_clean();
            $args_str.= $k.'='.(string)$vl."\n";

        }
        $url .= ': '.$args_str."\n";
        $this->urls[] = $url;
        return $args;
    }

    function pre_http_request($return,$r, $url)
    {
        if(in_array($url, $this->urls) && $return===true)
        {
            $k = array_search($url, $this->urls);
            unset($this->urls[$k]);
        }
        return $return;
    }
}