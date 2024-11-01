<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Tr_Scan_Dir {

    var $log = '';
    var $count_dir = 0;
    var $count_file = 0;
    var $count_scanned_file = 0;
    var $dir = '';
    var $skip_ext = array();
    var $scan_custom = '';
    var $threats_found = array();
    var $files = array();
    var $log_time_file = '';
    var $new_scan = false;

    function  Tr_Scan_Dir()
    {
        $this->log_time_file = WP_CONTENT_DIR.'/cache/log_time_file.json';
    }

    function sync_server()
    {
        global $wpdb;

        $results = $wpdb->get_results("select * from wp_tr_file_logs where synced = 0 limit 100 ",ARRAY_A);
        if(count($results)==0)
        {
            return false;
        }
        $for = str_replace(array('http://','https://'),'',get_bloginfo('url'));
        $body = array();
        $body['tr_action'] = 'tr_sync_security_log_files';
        $body['data'] = base64_encode(json_encode($results));
        $body['for']= base64_encode($for);
        $rs = wp_remote_post(TRSCSC_SERVER, array('body'=>$body,'timeout'=>100,
            'user-agent'=>'Mozilla/5.0 (Windows NT 6.2; WOW64; rv:24.0) Gecko/20100101 Firefox/24.0',
            'headers'=>array('Referer' => TRSCSC_SERVER)));
        if (!is_wp_error($rs)) {
            $data = @json_decode($rs['body'], true);
            if($data['status']=='ok'){
                $ids = array();
                foreach($results as $r)
                {
                    $ids[] = $r['id'];
                }
                $ids = implode(',',$ids);
                $wpdb->query("delete from wp_tr_file_logs where id in ({$ids}) ");
            }
        }
        return $rs;
    }

    function get_black_list()
    {
        global $wpdb;
        $body = array();
        $body['tr_action'] = 'ci_get_black_list';
        $for = str_replace(array('http://','https://'),'',get_bloginfo('url'));
        $body['for']= base64_encode($for);
        $rs = wp_remote_post(TRSCSC_SERVER,array('body'=>$body,'timeout'=>100,
            'user-agent'=>'Mozilla/5.0 (Windows NT 6.2; WOW64; rv:24.0) Gecko/20100101 Firefox/24.0',
            'headers'=>array('Referer' => TRSCSC_SERVER)));

        if (!is_wp_error($rs)) {
            $data = @json_decode($rs['body'], true);

            $bantime = $data['bantime']> time()+86400? $data['bantime'] : time() + 86400 * 100;
            foreach($data['blacklist'] as $ip)
            {
                $sql = "INSERT INTO wp_tr_lock_ip (ip, bantime) VALUES('{$ip}', {$bantime}) 
                                ON DUPLICATE KEY UPDATE bantime = {$bantime} ";
                //var_dump($sql);exit;
                $wpdb->query($sql);
            }
        }
    }


    function scan($dir, $options) {
        $this->dir = $dir;
        $this->skip_ext = explode(',', $options['skip_ext']);
        $this->skip_ext[] = 'buildpath';
        if (empty($this->scan_custom)) {
            include('default_scan_custom.php');
            $this->scan_custom = $default_scan_custom;
        }
        $this->scan_dir($dir);
    }
    
    function count_dir($dir)
    {
        $count = 1;
        if (is_dir($dir)) {
            $handle = @opendir($dir);
            while (false !== ($entry = @readdir($handle))) {
                if ($entry != "." && $entry != "..") {
                    $path = $dir . '/' . $entry;
                    if (is_dir($path)) {
                        $count+= $this->count_dir($path);
                    }
                }
            }
            @closedir($handle);
        }
        return $count;
    }

    function scan_dir($dir) {
        $files = array();
        if (is_dir($dir)) {
            $handle = @opendir($dir);
            while (false !== ($entry = readdir($handle))) {
                if ($entry != "." && $entry != "..") {
                    $path = $dir . '/' . $entry;
                    if (is_dir($path)) {
                        $this->count_dir ++;
                        $this->scan_dir($path);
                    } else {
                        $this->count_file++;
                        $this->scan_file($path);
                    }
                }
            }
            @closedir($handle);
        }
    }

    function scan_file($file) {
        $filesize = @filesize($file);
        $ext = pathinfo($file, PATHINFO_EXTENSION);
        if (!$filesize || in_array($ext, $this->skip_ext))
            return;
        $file_content = @file_get_contents($file);
        //var_dump($ext);exit;
        foreach ($this->scan_custom as $threat_name => $threat_definitions) {
            if (is_array($threat_definitions) && count($threat_definitions) > 1 && strlen(array_shift($threat_definitions)) == 5) {
                while ($threat_definition = array_shift($threat_definitions)) {
                    if ($found = @preg_match_all($threat_definition, $file_content, $threats_found)) {
                        foreach ($threats_found[0] as $find) {
                            $this->threats_found[$file] = $threat_name;
                        }
                        
                    }
                    
                }
            }
        }
        $this->count_scanned_file++;
    }

    function get_log() {
        return $this->threats_found;
    }

    function scan_log_time()
    {

        $this->start_time = microtime(true);
        $this->files = @json_decode(@file_get_contents($this->log_time_file),true);
        if(count($this->files)==0)
        {
            $this->new_scan = true;
        }
        $this->scan_dir_time(ABSPATH.'/');
        @file_put_contents($this->log_time_file,json_encode($this->files));
    }
    
    function scan_dir_time($root,$dir='')
    {
        global $wpdb;
        if (is_dir($root.$dir)) {
            $handle = @opendir($root.$dir);
            if($handle)
            {
                while (false !== ($entry = @readdir($handle)))
                {
                    if ($entry != "." && $entry != ".." && $entry!='') {
                        $path = $dir.'/'.$entry;
                        if (is_dir($root.$path)) {

                            if(strpos($path,'/cache')!==false || strpos($path,'/uploads')!==false)
                            {
                                continue;
                            }


                            $this->scan_dir_time($root,$path);

                        } else {
                            if(stripos($entry,'.php')>0)
                            {
                                $time = filemtime($root.$path);
                                $size = filesize($root.$path);

                                if(!$this->new_scan)
                                {
                                    $ftype = '';
                                    if(is_array($this->files[$path]))
                                    {
                                        $oldtime = $this->files[$path][0];
                                        $oldsize = $this->files[$path][1];
                                    }else{
                                        $oldtime = $this->files[$path];
                                        $oldsize = 0;
                                    }

                                    if(!isset($this->files[$path]))
                                    {
                                        $ftype = 'N';
                                    }else if($oldtime < $time && $size != $oldsize){
                                        $ftype = 'C';
                                    }
                                    else if($oldtime < $time){
                                        $ftype = 'T';
                                    }

                                    if(!empty($ftype))
                                    {
                                        $data = array(
                                            'file'=>$path,
                                            'ftype'=>$ftype,
                                            'changed'=>date('Y-m-d H:i:s',$time),
                                            'sizechanged'=> $size - $oldsize
                                        );
                                        if($this->files[$path])
                                        {
                                            $data['lasttime'] = date('Y-m-d H:i:s',$oldtime);
                                        }
                                        $wpdb->insert('wp_tr_file_logs',$data);
                                    }
                                }
                                $this->files[$path] = array($time,$size);
                            }
                        }
                    }
                }
            }

            @closedir($handle);
        }
    }

}


