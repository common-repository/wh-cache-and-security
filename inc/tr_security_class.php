<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Tr_Security_Class
{
    var $checked_remote = false;
    var $options = array();
    var $is_admin = false;

    public function __construct()
    {
        add_filter('authenticate',array(&$this,'authenticate'),99,3);
        add_action('login_init', array(&$this,'login_init'));
        add_action('init',array(&$this,'init'));
        add_action('login_form',array(&$this,'login_form'));
        add_action('woocommerce_login_form',array(&$this,'login_form'));
        add_action('lostpassword_form',array(&$this,'lostpassword_form'));
        add_action('lostpassword_post',array(&$this,'lostpassword_post'));
        add_action('register_form',array(&$this,'register_form'));
        add_action('woocommerce_register_form',array(&$this,'register_form'));
        add_filter('registration_errors', array(&$this,'registration_errors'));
        add_filter('comment_form_field_comment', array(&$this,'comment_form'),99);
        add_filter('preprocess_comment', array(&$this, 'preprocess_comment'), 1);

        add_action('tr-captcha',array(&$this, 'captcha_field'));
        add_filter('tr-captcha-validate',array(&$this,'captcha_validate_code'));
        add_filter('clear_auth_cookie',array(&$this,'clear_auth_cookie'));
        add_action('wp_logout',array(&$this,'wp_logout'));

        if(is_admin())
        {
            $this->is_admin = true;
        }
        if(!$this->is_admin)
        {
            if( defined('FORCE_SSL_FRONT') && FORCE_SSL_FRONT )
            {
                $this->use_ssl(true);
            }
        }else{
            //check ban ip
            $this->check_ban();
        }

        $this->options = $this->get_config();


        if($this->options['log_curl'])
        {
            new Tr_Log_Net();
        }

        if($this->options['log_time'])
        {
            new Tr_Log_Time();
        }

        if($this->options['disable_call_wp_api'] && stripos($_SERVER['REQUEST_URI'],'plugins.php')===false)
        {
            remove_action( 'admin_init', '_maybe_update_core' );
            remove_action( 'wp_version_check', 'wp_version_check' );
            remove_action( 'admin_init', '_maybe_update_plugins' );
            remove_action( 'admin_init', '_maybe_update_themes' );
            remove_action( 'load-themes.php', 'wp_update_themes' );
            remove_action( 'load-update.php', 'wp_update_themes' );
            remove_action( 'load-update-core.php', 'wp_update_themes' );
            remove_action( 'wp_update_themes', 'wp_update_themes' );
            remove_action( 'init', 'wp_schedule_update_checks' );
        }

        if($this->options['disable_backend_cron'] && $this->is_admin && (!defined('DOING_CRON') || !DOING_CRON))
        {
            @define('DISABLE_WP_CRON',true);
        }

        if($this->options['disable_front_cron'] && !$this->is_admin && (!defined('DOING_CRON') || !DOING_CRON))
        {
            @define('DISABLE_WP_CRON',true);
        }

    }

    public function get_config($key=false)
    {
        global $blog_id;

        if (!$this->options) {
            $this->options = get_option('tr_security', array());

            //add whitelist network
            if(is_multisite())
            {
                $net = get_blog_option(1, 'trcs_nw_security', array());
                $wl = (array)$net['white_ips_array'];
                if(is_array($this->options['white_ips_array']))
                {
                    $this->options['white_ips_array'] = array_merge($this->options['white_ips_array'],$wl);
                }else{
                    $this->options['white_ips_array'] = $wl;
                }

            }
        }
        if($key)
        {
            return $this->options[$key];
        }
        return $this->options;
    }

    public function get_ip()
    {
        if(!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {

            $ips = explode(',',$_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($ips[0]);
            if(!empty($ip))return $ip;
        }

        if(!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        return $ip;
    }


    function get_lock_ip_remote($args)
    {
        if ($this->checked_remote == true)
            return;
        $u = $ip = $loginfail = $locked = $s = '';
        extract(wp_parse_args($args,array(
            'ip' => '',
            'loginfail' => 1,
            'locked'    => 0,
            'u'         => '',
            's'         => 0,
        )));
        $u = @base64_encode($u);
        $for = str_replace(array('http://','https://'),'',get_bloginfo('url'));
        $body = array();
        $body['tr_action'] = 'get_lock_ip_remote';
        $body['ip'] = $ip;
        $body['for']= urlencode($for);
        $body['lf'] = $loginfail;
        $body['ld'] = $locked ? '1' : '0';
        $body['u']  = $u;
        $body['s']  = $s;
        $rs = wp_remote_get(TRSCSC_SERVER.'?'.http_build_query($body), array('timeout' => 3));

        $return = false;
        if (!is_wp_error($rs)) {

            $this->checked_remote = true;
            $data = @json_decode($rs['body'], true);

            if (is_array($data)) {


                if ($data['ips'] && is_array($data['ips'])) {
                    foreach ($data['ips'] as $row) {
                        $this->add_count_ip($row['ip'], $row['count'], $row['time']);
                        if ($ip == $row['ip']) {
                            $return = true;
                        }
                    }
                }

                //update ban ip
                $has_change_banip = false;
                if (isset($data['removebanipall'])) {
                    $this->updatebantime(0,'all');
                    $has_change_banip = true;
                }

                if (isset($data['banips']) && is_array($data['banips'])) {
                    foreach ($data['banips'] as $i => $ipdata) {
                        $banip = trim($ipdata['ip']);
                        $bantime = $ipdata['bantime'];
                        if (!empty($banip)) {
                            $this->updatebantime($bantime,$banip);
                            $has_change_banip = true;
                        }
                        if ($banip == $ip)
                            $return = true;
                    }
                }

                if (isset($data['removebanips']) && is_array($data['removebanips'])) {
                    foreach ($data['removebanips'] as $i => $banip) {
                        $this->updatebantime(0,$banip);
                        $has_change_banip = true;
                    }
                }

                /**
                if ($has_change_banip) {
                //need change
                if(!function_exists('trfront_action_change_htaccess'))
                include_once (TRSCSC_PATH . 'inc/actions.php');
                trfront_action_change_htaccess();
                }
                 */

                if(isset($data['whitelist']) && count($data['whitelist'])>0)
                {
                    foreach($data['whitelist'] as $ipwhite)
                    {
                        if(!empty($ip) && $ip == $ipwhite)
                        {
                            $return = -1;
                        }
                    }

                }
            }
        }
        return $return;
    }

    function in_whitelist($ip)
    {

        $list    = $this->get_config('white_ips_array');
        $match_ip = false;
        if(isset($_REQUEST['tr_show_ips'])){
            var_dump($list);exit;
        }
        if(!is_array($list))return $match_ip;
        foreach($list as $lip)
        {
            if($lip==$ip)
            {
                $match_ip = true;
                break;
            }
            else if( stripos($lip,'*')!==false)
            {
                $match = str_replace('.','\.',$lip);
                $match = str_replace('*','[0-9]+',$match);
                if(preg_match('/'.$match.'/',$ip,$matches))
                {
                    $match_ip = true;
                    break;
                }
            }
        }
        return $match_ip;
    }

    function auth_cookie_expiration($expiration, $user_id, $remember){

        $hours = tr_get_option('tr_security', 'login_expiry');
        $hours_remember = tr_get_option('tr_security', 'login_remember_expiry');
        //if "remember me" is checked;
        if ( $remember && $hours_remember>0) {
            $expiration = 60*60*24*$hours_remember;
        } else if($hours>0 && !$remember){
            //WP defaults
            $expiration = 60*60*24*$hours;
            add_action('set_auth_cookie', array(&$this,'set_auth_cookie'),99,5);
            add_action('set_logged_in_cookie', array(&$this,'set_logged_in_cookie'),99,5);
            add_action('wp_login', array(&$this,'wp_login'),99,2);
        }

        //http://en.wikipedia.org/wiki/Year_2038_problem
        if ( PHP_INT_MAX - time() < $expiration ) {
            //Fix to a little bit earlier!
            $expiration =  PHP_INT_MAX - time() - 5;
        }

        return $expiration;
    }

    function set_auth_cookie($auth_cookie, $expire, $expiration, $user_id, $scheme )
    {
        if ( $scheme =='secure_auth' ) {
            $auth_cookie_name = SECURE_AUTH_COOKIE;
        } else {
            $auth_cookie_name = AUTH_COOKIE;
        }
        $this->auth_cookie_name = $auth_cookie_name;
        $this->auth_cookie = $auth_cookie;
        $this->expire_auth = $expiration + ( 12 * HOUR_IN_SECONDS );
    }
    function set_logged_in_cookie($logged_in_cookie, $expire, $expiration, $user_id, $scheme )
    {
        $this->expire_auth = $expiration + ( 12 * HOUR_IN_SECONDS );
        $this->logged_in_cookie = $logged_in_cookie;

        if($this->get_config('login_cookie_js'))
        {
            setcookie('ci_user_loggedin', '1', $this->expire_auth, COOKIEPATH, COOKIE_DOMAIN, is_ssl());
        }
    }

    function wp_login($user_login, $user )
    {
        $secure = is_ssl();
        $secure_logged_in_cookie = $secure && 'https' === parse_url( get_option( 'home' ), PHP_URL_SCHEME );

        if(!empty($this->auth_cookie_name))
        {
            setcookie($this->auth_cookie_name, $this->auth_cookie, $this->expire_auth, PLUGINS_COOKIE_PATH, COOKIE_DOMAIN, $secure, true);
            setcookie($this->auth_cookie_name, $this->auth_cookie, $this->expire_auth, ADMIN_COOKIE_PATH, COOKIE_DOMAIN, $secure, true);
            setcookie(LOGGED_IN_COOKIE, $this->logged_in_cookie, $this->expire_auth, COOKIEPATH, COOKIE_DOMAIN, $secure_logged_in_cookie,true);
            if ( COOKIEPATH != SITECOOKIEPATH )
                setcookie(LOGGED_IN_COOKIE, $this->logged_in_cookie, $this->expire_auth, SITECOOKIEPATH, COOKIE_DOMAIN, $secure_logged_in_cookie,true);
        }
    }

    function clear_auth_cookie()
    {
        setcookie( 'ci_user_loggedin',' ', time() - YEAR_IN_SECONDS, COOKIEPATH,   COOKIE_DOMAIN );
    }

    function authenticate($user, $username, $password)
    {
        global $wpdb;

        if(empty($username))return $user;


        if($this->get_config('login_expiry')>0 || $this->get_config('login_remember_expiry')>0)
        {
            add_filter('auth_cookie_expiration', array(&$this,'auth_cookie_expiration'), 99, 3);
        }
        if($this->get_config('login_cookie_js')){
            add_action('set_logged_in_cookie', array(&$this,'set_logged_in_cookie'),99,5);
        }

        $ip             = $this->get_ip();
        $current_time   = time();

        if($this->in_whitelist($ip))
        {
            return $user;
        }

        //disable admin
        if($username=='admin'){
            return new WP_Error('admin_login_user', __('<strong>Warning</strong>: Use Your email to login'));
        }


        if($this->get_config('captcha_login'))
        {
            $result = $this->captcha_validate_code();
            if($result!==true)
            {
                if($this->get_config('captcha_login_back') && !empty($_SESSION['trnocaptcha']))
                {
                    if($_SESSION['trnocaptcha'] == $_POST['captcha'])
                    {
                        $result = true;
                    }
                }
            }
            if($result !==true)
            {
                if(!is_wp_error($user))$user = new WP_Error();
                $user->add('captcha', "<strong>".__('ERROR')."</strong>: {$result}" );
            }
        }

        if (!$this->get_config('login_limit_enable'))
        {
            if ((!$user || is_wp_error($user)) ) {
                $check_result = $this->get_lock_ip_remote(array('ip'=>$ip,'u'=>$username));
            }
            return $user;
        }

        //check referer
        if(empty($_SERVER['HTTP_REFERER']) || stripos($_SERVER['HTTP_REFERER'],$_SERVER['HTTP_HOST'])===false)
        {
            $this->add_count_ip($ip,0,0,1);
            echo 'r';
            exit;
        }

        //check ban login host
        $log_ip = $wpdb->get_row("select * from wp_tr_lock_ip where ip = '{$ip}'");
        if ($log_ip && $this->get_config('max_login_host') > 0 &&
            ( $log_ip->loginfail>=$this->get_config('max_login_host') || $log_ip->lasttime > $current_time ) )
        {
            if ($log_ip->lasttime == 0) {
                $log_ip->lasttime = $current_time;
            }
            if ($log_ip->lasttime > $current_time - $this->get_config('login_time_period')*60) {

                $check_result = $this->get_lock_ip_remote(array('ip'=>$ip,'locked'=>1,'u'=>$username));

                if($check_result===-1)
                {
                    return $user;
                }else if ($check_result===true) {
                    $this->add_count_ip($ip);
                    $this->show_ban($ip,'cr');
                    exit;
                }

                if ($this->get_config('login_email_notification') && $log_ip->loginfail == $this->get_config('max_login_host')) {
                    $reason = 'A IP "' . $ip . '" has been locked out because ' . $log_ip->loginfail . ' times failed login attempts.';
                    $reason.= '. Username: '.$username;
                    $reason.= '. Pass: '.$password;
                    $this->log_msg($username, $ip, 'times failed login: ' . $log_ip->loginfail, 'ip');
                    $this->notify_mail($reason);
                }

                $this->add_count_ip($ip);
                return new WP_Error('max_login_user', __('<strong>ERROR</strong>: max failed login attempts reached wait ' .
                    $this->get_config('login_time_period') . ' minute(s)'));

            } else {
                $this->reset_count_ip($ip);
            }
        }

        $loginuser = get_user_by('login', $username);
        if (!$loginuser && is_email($username)) {
            $loginuser = get_user_by('email', $username);
        }
        if ($loginuser):
            //check ban login username
            $log = get_user_meta($loginuser->ID, '_tr_security', true);
            if ($this->get_config('max_login_user') > 0 && @$log['login_failed'] >= $this->get_config('max_login_user')) {
                if ($log['login_failed_time'] > $current_time - $this->get_config('login_time_period') * 60) {

                    $check_result = $this->get_lock_ip_remote(array('ip'=>$ip,'locked'=>1,'u'=>$username));
                    if($check_result===-1)
                    {
                        return $user;
                    }else if ($check_result===true) {
                        $this->add_count_ip($ip);
                        $this->show_ban($ip,'cr2');
                        exit;
                    }

                    if ($this->get_config('login_email_notification') && $log['sent_mail'] != 1) {
                        $reason = 'A User "' . $username . '" has been locked out because ' . $log['login_failed'] .
                            ' times failed login attempts. IP: ' . $ip;
                        $this->log_msg($username, $ip, 'times failed login: ' . $log['login_failed']);
                        $this->notify_mail($reason);
                    }
                    $this->add_count_ip($ip);
                    $this->add_count_user($loginuser->ID,intval(@$log['login_failed']) + 1,$current_time,$ip,1);
                    return new WP_Error('max_login_user', __('<strong>ERROR</strong>: max failed login attempts reached wait ' .
                        $this->get_config('login_time_period') . ' minute(s) or reset your password'));
                } else {
                    $this->add_count_user($loginuser->ID,0,$current_time,$ip);
                }
            }
        endif;

        if (!$user || is_wp_error($user)) {
            $check_result = $this->get_lock_ip_remote(array('ip'=>$ip,'u'=>$username));
            if($check_result===-1)
            {
                return $user;
            }
            else if ($check_result===true) {
                $this->add_count_ip($ip);
                $this->show_ban($ip,'cr3');
                exit;
            }
            $this->add_count_ip($ip);
            if ($loginuser) {
                $this->add_count_user($loginuser->ID,intval(@$log['login_failed']) + 1,$current_time,$ip);
            }

        } else {
            //reset
            if ($loginuser) {
                $this->add_count_user($loginuser->ID,0,0,$ip);
            }
            $this->reset_count_ip($ip);
        }

        return $user;
    }

    public function check_ban()
    {
        global $wpdb;
        $ip = $this->get_ip();
        $current_time = time();
        $log_ip = $wpdb->get_row("select * from wp_tr_lock_ip where ip = '{$ip}' AND bantime >= {$current_time}");
        //var_dump($ip);exit;
        if($log_ip)
        {
            $this->show_ban($ip,'b');
            exit;
        }
    }

    function login_init()
    {
        global $wpdb;
        add_filter('site_url' , array(&$this,'site_url'),99,3);
        add_filter('network_site_url' , array(&$this,'site_url'),99,3);

        $action = isset($_REQUEST['action']) ? $_REQUEST['action'] : 'login';
        $ip     = $this->get_ip();
        $current_time = time();
        //check ban
        if($this->in_whitelist($ip))return;

        $baned = $wpdb->get_var("select ip from wp_tr_lock_ip where ip='{$ip}' and bantime >= {$current_time}");
        if ($baned) {
            $this->show_ban($ip);
            exit;
        }

        if(!$this->get_config('hide_backend'))
        {
            if($action=='login' && $this->get_config('enable_check_cookie'))
            {
                $this->check_bot($ip);
            }
            return false;
        }


    }

    function captcha_field($style=0)
    {
        if($style==1):?>
            <div class="frow">
                <label for="captcha_code"><?php _e('CAPTCHA Code') ?></label>
                <input type="text" name="captcha_code" id="captcha_code" class="input required" required value="" size="20" autocomplete="off" />
                <?php $this->show_catcha()?>

            </div>
        <?php else:?>
            <p>
                <label for="captcha_code"><?php _e('CAPTCHA Code') ?><br />
                    <input type="text" name="captcha_code" id="captcha_code" class="input required" required value="" size="20" autocomplete="off" />
                    <?php $this->show_catcha()?>
                </label>
            </p>
            <?php
        endif;

    }

    function login_form()
    {
        if($this->get_config('captcha_login_back') && !empty($_SESSION['trnocaptcha']))
        {
            echo '<input type="hidden" name="captcha" value="'.$_SESSION['trnocaptcha'].'"/>';
        }
        else if($this->get_config('captcha_login'))
        {
            $this->captcha_field();
        }
    }

    function wp_logout()
    {
        if($this->get_config('captcha_login_back'))
        {
            $_SESSION['trnocaptcha'] = md5(time());
        }
    }

    function lostpassword_form()
    {
        if($this->get_config('captcha_password'))
        {
            $this->captcha_field();
        }
    }

    function register_form()
    {
        if($this->get_config('captcha_register'))
        {
            $this->captcha_field();
        }
    }

    function comment_form($text='')
    {
        if($this->get_config('captcha_comment'))
        {
            if (is_user_logged_in()) {
                return $text;
            }
            ob_start();
            ?>
            <p>
                <?php if($this->get_config('captcha_comment_label')):?>
                    <label for="captcha_code"><?php _e('CAPTCHA Code') ?> </label>
                <?php endif;?>
                <input type="text" name="captcha_code" id="captcha_code" class="txt inputbox textbox" value="" size="30" autocomplete="off" />
                <?php if(!$this->get_config('captcha_comment_label')):?>
                    <label for="captcha_code"><?php _e('CAPTCHA Code') ?> </label>
                <?php endif;?>
                <?php $this->show_catcha()?>
            </p>
            <?php
            $text.= ob_get_clean();
        }
        return $text;
    }

    function preprocess_comment($comment)
    {
        if($this->get_config('captcha_comment'))
        {
            if ( function_exists('WPWall_Widget') && isset($_POST['wpwall_comment']) ) {
                return $comment;
            }
            if (is_user_logged_in()) {
                return $comment;
            }
            $result = $this->captcha_validate_code();
            if($result !==true)
            {
                wp_die( "<strong>".__('ERROR')."</strong>: {$result}" );
            }
        }
        return $comment;
    }

    function lostpassword_post()
    {
        if($this->get_config('captcha_password'))
        {
            $result = $this->captcha_validate_code();
            if($result !==true)
            {
                wp_die( "<strong>".__('ERROR')."</strong>: {$result}" );
            }
        }
    }

    function registration_errors($errors)
    {
        if($this->get_config('captcha_register'))
        {
            $result = $this->captcha_validate_code();
            if($result !==true)
            {
                if(!is_object($errors) || is_array($errors))
                {
                    $errors[] = "<strong>".__('ERROR')."</strong>: {$result}";
                }
                else{
                    $errors->add('captcha', "<strong>".__('ERROR')."</strong>: {$result}" );
                }
            }
        }
        return $errors;
    }

    function captcha_validate_code()
    {
        if(empty($_POST['captcha_code']))
        {
            return __('Empty CAPTCHA');
        }
        else if (PhpCaptcha::Validate($_POST['captcha_code'])==false)
        {
            return __('Wrong CAPTCHA');
        }
        return true;
    }

    function show_catcha()
    {
        $code = time();
        $url = TRSCSC_URL.'s.php?s=img&c='.$code;
        ?>
        <img id="captcha_img" src="<?php echo $url?>" alt=""/>
        <a href="#" rel="nofollow" title="<?php echo esc_attr(__('Refresh Image'))?>"
           onclick="document.getElementById('captcha_img').src='<?php echo $url?>'+Math.random();return false;">
            <img src="<?php echo TRSCSC_URL?>images/refresh.gif" alt="" />
        </a>
        <?php
    }

    function check_bot($ip)
    {
        global $wpdb;

        if(!@session_id())@session_start();


        if(!@session_id())return;

        $current_time = time();

        if(!empty($_GET['cc']) && $_GET['cc']==$_SESSION['tr_sec_auto_codecheck'])
        {
            $_SESSION['tr_sec_auto'] =$current_time;
        }
        else if(!isset($_SESSION['tr_sec_auto']) || $_SESSION['tr_sec_auto'] < $current_time - 86400)
        {
            $codecheck = wp_generate_password(12,false);
            $_SESSION['tr_sec_auto_codecheck'] = $codecheck;
            $url = add_query_arg('cc',$codecheck);
            if(stripos($url,'wp-login')===false)
            {
                $url = $_SERVER['REQUEST_URI'];
                $url = $url . ((strpos($url,'?')===false)? '?':'&'). 'cc='.$codecheck;
                if(stripos($url,'wp-login')===false)
                {
                    $url = '?'.$_SERVER['QUERY_STRING'].'&cc='.$codecheck;

                }
            }

            if(strtolower($_SERVER['REQUEST_METHOD'])=='post')
            {
                $rs = $this->add_count_ip($ip,0,0,1);
                if($rs['cookiefail']==1)
                {
                    //need check from server
                    $check_result = $this->get_lock_ip_remote(array('ip'=>$ip,'locked'=>0,'s'=>1));
                    if ($check_result===true) {
                        $this->log_msg('admin', $ip, 'try to login with out cookie, ban by server', 'ip',$current_time);
                        $this->show_ban($ip,'ck4');
                        exit;
                    }

                }else if($rs['cookiefail']>=5)
                {
                    //need ban this ip
                    $bantime = $current_time + 86400 * 2;//2days
                    $this->updatebantime($bantime,$ip);
                    $this->show_ban($ip,'ck5');
                    exit;
                }
            }
            if(isset($_REQUEST['cc'])){
                return;
            }
            ?>
            <meta http-equiv="refresh" content="0; url=<?php echo $url?>">
            <?php
            exit;
        }
    }

    function site_url($url, $path, $scheme)
    {
        if($path=='wp-login.php' || $path=='wp-login.php?action=lostpassword' || $path=='wp-login.php?action=register')
        {
            $key = get_option('tr_security_admin_key');
            $url.= (stripos($url,'?')===false? '?':'&') . $key;
        }
        return $url;
    }

    function log_msg($username, $ip, $msg = '', $type = 'user', $time = '')
    {
        global $wpdb;
        if (empty($time))
            $time = time();
        $log = array(
            'ltime' => $time,
            'username' => $username,
            'ip' => $ip,
            'ltype' => $type,
            'msg' => $msg);
        $wpdb->insert('wp_tr_security_log', $log);
    }

    function notify_mail($reason,$intime='')
    {
        $email = $this->get_config('login_email');
        if (!is_email($email))
            $email = get_bloginfo('admin_email');

        $subject = '[' . get_option('siteurl') . '] ' . __('Site Lockout Notification');
        if(empty($intime)) $intime = ' in '.$this->get_config('login_time_period') . ' minute(s) ';
        $msg = $reason . ' ' . $intime . "\n ";
        $msg .= "At: " . get_bloginfo('url') . "\n";
        $msg .= "WP Security by Trinh Team";
        wp_mail($email, $subject, $msg);
    }

    function add_count_ip($ip, $loginfail = 0, $time = 0, $cookiefail=0)
    {
        global $wpdb;
        if ($time == -10) {
            $wpdb->query("delete from wp_tr_lock_ip where ip = '{$ip}'");
            return;
        }
        if ($time == 0 || $time < time())
            $time = time();

        $exists = $wpdb->get_row("select * from wp_tr_lock_ip where ip = '{$ip}'");
        $data = array(
            'loginfail' => $loginfail,
            'lasttime' => $time,
            'ip' => $ip,
            'cookiefail' => $cookiefail
        );

        if ($exists) {
            if ($loginfail == 0) {
                $data['loginfail'] = $exists->loginfail + 1;
            }
            if ($exists->loginfail > $data['loginfail']) {
                $data['loginfail'] = $exists->loginfail;
            }
            if ($exists->lasttime > $data['lasttime'])
                unset($data['lasttime']);

            $data['cookiefail'] = $exists->cookiefail + $cookiefail;

            $wpdb->update('wp_tr_lock_ip', $data, array('ip' => $ip));
        } else {
            if ($loginfail == 0)
                $data['loginfail'] = 1;
            $wpdb->insert('wp_tr_lock_ip', $data);
        }
        return $data;
    }

    function add_count_user($userid,$loginfail=0,$time=0,$ip='',$sent_mail=0)
    {
        $log['login_failed'] = $loginfail;
        $log['login_failed_time'] = $time;
        $log['ip'] = $ip;
        $log['sent_mail']=$sent_mail;
        update_user_meta($userid, '_tr_security', $log);
    }

    function allow_user_login($user)
    {
        $this->add_count_user($user->ID);
    }

    function updatebantime($time,$ip)
    {
        global $wpdb;
        $data = array('bantime'=>$time);
        $where = array('ip' => $ip );
        if($ip=='all')
        {
            unset($where['ip']);
        }

        $rs = $wpdb->update('wp_tr_lock_ip',$data,$where);
        if($rs==false && !empty($where['ip']))
        {
            $wpdb->insert('wp_tr_lock_ip',array('ip'=>$ip,'bantime'=>$time));
        }
    }

    function reset_count_ip($ip)
    {
        global $wpdb;
        $exists = $wpdb->get_row("select * from wp_tr_lock_ip where ip = '{$ip}'");

        if ($exists)
        {
            $max_fail = $this->get_config('max_login_user') > 0 ? $this->get_config('max_login_user') : 7;
            if ($exists->loginfail <= $max_fail || $exists->lasttime < time() - 86400*2 )
            {
                $wpdb->update('wp_tr_lock_ip', array('loginfail' => 0,'cookiefail'=>0), array('ip' => $ip));
            }
            $_SESSION['tr_secu_reset_ip'] = $ip;
        }
    }

    function use_ssl($ssl = false)
    {
        //return if post method
        if (is_array($_POST) && count($_POST) > 0)
            return;

        if ($ssl && !is_ssl()) {
            if (0 === strpos($_SERVER['REQUEST_URI'], 'http')) {
                header('location:'.preg_replace('|^http://|', 'https://', $_SERVER['REQUEST_URI']));
            } else {
                header('location:'.'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
            }
            exit;
        } else
            if (!$ssl && is_ssl()) {
                if (0 === strpos($_SERVER['REQUEST_URI'], 'http')) {
                    header('location:'.preg_replace('|^https://|', 'http://', $_SERVER['REQUEST_URI']));
                } else {
                    header('location:'.'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
                }
                exit;
            }
    }

    public function fix_ssl($content)
    {
        $content = str_replace('http://','//',$content);
        $content = str_replace('http:\/\/','\/\/',$content);
        return $content;
    }

    function is_ssl()
    {
        return (is_ssl() || (isset($_SERVER['HTTP_CF_VISITOR']) && stripos($_SERVER['HTTP_CF_VISITOR'],'https')>0));
    }

    public function init()
    {
        global $wpdb;
        $ip     = $this->get_ip();
        if(!is_admin() &&  $this->get_config('fix_ssl') && $this->is_ssl())
        {
            @ob_start(array(&$this,'fix_ssl'));
        }

        if(isset($_GET['unlockemail']) && $_GET['ip']==$ip){
            $code = $_GET['unlockemail'];
            $wpdb->update('wp_tr_lock_ip',array('bantime'=>0,'unlock'=>'','loginfail'=>0),array('ip'=>$ip));
            wp_redirect(wp_login_url());
            exit;
        }
        else if(isset($_POST['unlock_form'])){


            $result = $this->captcha_validate_code();
            if($result===true)
            {
                $usern = $_POST['usern'];
                $user = get_user_by('login',$usern);
                if(!$user)
                {
                    $user = get_user_by('email',$usern);
                }
                // var_dump($user);exit;
                if($user)
                {
                    $email = $user->user_email;
                    $code = md5(time());
                    $wpdb->update('wp_tr_lock_ip',array('unlock'=>$code),array('ip'=>$ip));

                    $link = get_bloginfo('home').'/wp-login.php?unlockemail='.$code.'&ip='.$ip;
                    $subject = 'Unlock login';
                    $msg = 'Link: '.$link;
                    $rs = wp_mail($email,$subject,$msg);
                    if(!$rs)
                    {
                        echo '<div>Email server error</div>';
                        exit;
                    }
                }else{

                }
                echo '<div style="color:blue">Please check your email box to unlock.</div>';

            }else{
                echo '<div style="color:red">wrong captcha</div>';
            }

            $this->show_ban($ip);
            exit;

        }
    }

    public function show_ban($ip='',$type='')
    {
        ?>
        <form method="post" data-type="<?php echo $type?>">
            <label>Username/Email: </label>
            <input type="usern" name="usern" style="width:200px;"/>
            <input type="hidden" name="ip" value="ip">
            <input type="hidden" name="unlock_form" value="test">
            <br>
            <label>Captcha: </label>
            <input type="text" name="captcha_code" id="captcha_code" class="txt inputbox textbox" value=""  style="width:200px" autocomplete="off" />
            <?php $this->show_catcha()?>
            <br><br>
            Your IP: <?php echo $ip?>
            <br><br>
            <input type="submit" value="Unlock"/>
        </form>
        <?php
    }

}
