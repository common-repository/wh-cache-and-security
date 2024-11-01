<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

global $blog_id, $tr_cache_blog_options,$tr_security;

include(TRSCSC_PATH.'inc/tr_security_class.php');
$tr_security = new Tr_Security_Class();

if(isset($tr_cache_blog_options[$blog_id]) && !function_exists('tr_cache_exit')){
    if(!@$tr_cache_blog_options[$blog_id]['in_init'] ){
        include_once(TRSCSC_PATH.'cache.php');
    }else{
        add_action('plugins_loaded','tr_cache_load_plugins_loaded',2);
        function tr_cache_load_plugins_loaded()
        {
            global $blog_id, $tr_cache_blog_options;
            include_once(TRSCSC_PATH.'cache.php');
        }
    }

}

add_action('init','tr_cache_plugin_init',9);
add_action('cache_security', 'tr_cache_security_clean');
add_action('wp_insert_comment','tr_wp_insert_comment',10,2);
add_action('validate_password_reset','tr_validate_password_reset',10,2);
add_action('save_post', 'tr_wp_insert_comment', 10,1);
add_action('wp_footer','trcs_wp_footer');
register_activation_hook(TRSCSC_FILE, 'tr_cache_security_activate');
register_deactivation_hook(TRSCSC_FILE, 'tr_cache_security_deactivate');

function tr_cache_plugin_init()
{
    if(!is_admin())
    {        
        if(!is_user_logged_in())
            add_filter( 'show_admin_bar', '__return_false' ,99); 
            
        global $tr_cache_options;
        if(!is_array($tr_cache_options))
            $tr_cache_options = get_option('trcs_cache',array());
            
        if($tr_cache_options['optimize_js'] || $tr_cache_options['optimize_css'])
        {
            $cache_obj = Tr_Cache_Class::instance();
            if(!Tr_Cache_Class::$has_run)
                add_action('template_redirect',array(&$cache_obj,'template_redirect'),1);
            if($tr_cache_options['add_script_sync']){
                add_filter('script_loader_tag',array(&$cache_obj,'script_loader_tag'),11,3);
            }
        }

        if($tr_cache_options['add_style_bottom'] || tr_cache_is_mobile()){
            remove_action( 'wp_head','wp_print_styles', 8);
        }
        if($tr_cache_options['optimize_js_footer'])
        {
            remove_action( 'wp_head','wp_print_head_scripts',9 );
        }
        
    }
    add_action('template_redirect','tr_template_redirect_check_404',1);
}

function tr_cache_is_mobile()
{
    $useragent=$_SERVER['HTTP_USER_AGENT'];
    return preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino/i',$useragent)||preg_match('/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i',substr($useragent,0,4));
}

function tr_cache_security_activate() {
    include(TRSCSC_PATH . 'inc/install.php');
}

function tr_cache_security_deactivate() {
    wp_clear_scheduled_hook('cache_security');

    // burn the file without delete it so one can rewrite it
    include_once(TRSCSC_PATH . 'inc/admin_actions.php');
    tradmin_action_generate_config(true);
}

function tr_template_redirect_check_404()
{
    if(is_404())
    {
        include(TRSCSC_PATH.'inc/404.php');
    }
}

function tr_cache_security_clean()
{
    include(TRSCSC_PATH.'inc/cache_security_clean.php');
}

function tr_wp_insert_comment($id, $comment='')
{
    if($comment)
    {
        $post_id = $comment->comment_post_ID;
    }else
    {
        $post_id = $id;
    }
    
    $link = get_permalink($post_id);
    $link = preg_replace( '~^.*?://~', '', $link );
    $file = md5($link);
    $filename = $file.'.dat';
    $mobilefile = $file.'_mobile.dat';
    @unlink(TRSCSC_CACHE_PATH.'/'.$filename);
    @unlink(TRSCSC_CACHE_PATH.'/'.$mobilefile);
    @unlink(TRSCSC_CACHE_PATH.'/'.$filename.'s');
    @unlink(TRSCSC_CACHE_PATH.'/'.$mobilefile.'s');
    
    @touch(TRSCSC_CACHE_PATH . '/_archives.dat');
    
}

function tr_validate_password_reset($errors, $user)
{
    global $tr_security;
    if($errors->get_error_code()=='')
    {
        $tr_security->allow_user_login($user);
    }
}

if(!function_exists('wp_is_writable'))
{
    function wp_is_writable( $path ) {
    	if ( 'WIN' === strtoupper( substr( PHP_OS, 0, 3 ) ) )
    		return win_is_writable( $path );
    	else
    		return @is_writable( $path );
    }
}


add_filter('pre_http_request','tr_pre_http_request2',99,3);
function tr_pre_http_request2($return,$r, $url)
{
    if(tr_get_option('tr_security', 'disable_call_wp_api') && stripos($_SERVER['REQUEST_URI'],'plugins.php')===false)
    {
        if(strpos($url,'version.wpbakery.com')>0)
        {
            return true;
        }
        else if(strpos($url,'themes/update-check')>0)
        {
            return true;
        }
        else if(strpos($url,'adminmenueditor')>0)
        {
            return true;
        }
    }
    return $return;
}


function trcs_wp_footer()
{
    $code = tr_get_option('trcs_otherst','footer_code');
    echo stripslashes($code);
}

function trcs_get_cache_class()
{
    include_once (TRSCSC_PATH.'inc/tr_cache_query.php');
    return new Tr_Cache_Query();
}

add_action('login_head','trcache_data_login_head',999);
function trcache_data_login_head()
{
    include(TRSCSC_PATH.'pages/login_head.php');
}
