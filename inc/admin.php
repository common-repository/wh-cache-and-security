<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

add_action('admin_init', 'tr_cache_security_admin_init');
add_action('save_post', 'tr_cache_invalidate_post', 0);
add_action('publish_post', 'tr_cache_invalidate_post', 0);
add_action('delete_post', 'tr_cache_invalidate_post', 0);
add_action('wp_set_comment_status', 'tr_cache_invalidate_comment', 0, 2);
add_action('wp_update_nav_menu', 'tr_cache_invalidate', 0);
add_action('switch_theme', 'tr_cache_invalidate', 0);
add_action('load-plugin-editor.php', 'tr_cache_load_plugin_editor', 0);
add_action('load-theme-editor.php', 'tr_cache_load_plugin_editor', 0);


$config = array(
    'menu' => array('top' => 'trcs_settings'), //sub page to settings page
    'slug' => 'trcs_settings',
    'page_title' => 'Cache & Security', //The name of this page 
    //'menu_title' => 'Cache & Security',
    'capability' => 'manage_options', // The capability needed to view the page
    'option_group' => 'trcs_cache', //the name of the option to create in the database
    'local_images' => true, // Use local or hosted images (meta box images for add/remove)
    // 'icon_url' => TRJM_URL.'images/mobile.png',
    'use_with_theme' => false, //change path if used with theme set to true, false for a plugin or anything else for a custom path(default false).
    'path' => dirname(dirname(__FILE__)),
    'usebackup' => true,
    //'network' => true,
    'tabs' => array(
        'this' => array('label' => 'Cache', 'menu' => 'Cache'),
        'trcs_security' => array('option' => 'tr_security', 'label' => 'Security', 'menu' => true),
        'trcs_otherst' => array('option' => 'trcs_otherst', 'label' => 'Other Settings', 'menu' => true,'network'=>false),
        'trcs_crons' => array('label' => 'Crons', 'menu' => true,'file'=>TRSCSC_PATH.'pages/admin_cron_page.php'),
        //'trcs_scan' => array('label' => 'Security Scan', 'menu' => true,'file'=>TRSCSC_PATH.'pages/admin_scan_page.php'),
        'trcs_db_optimize' => array('label' => 'Optimize DB', 'menu' => true,'file'=>TRSCSC_PATH.'pages/admin_db_optimize.php'),
        'trcs_image_optimize' => array('label' => 'Optimize Images', 'menu' => true,'file'=>TRSCSC_PATH.'pages/admin_image_optimize.php'),
        'trcs_status' => array('label' => 'Status', 'menu' => true,'file'=>TRSCSC_PATH.'pages/admin_status.php'),
    )
);

if(defined('WP_NETWORK_ADMIN') && WP_NETWORK_ADMIN===true)
{
    $config = array(
        'menu' => array('top' => 'trcs_settings'), //sub page to settings page
        'slug' => 'trcs_settings',
        'page_title' => 'CI Security', //The name of this page
        'capability' => 'edit_themes', // The capability needed to view the page
        'option_group' => 'trcs_nw_security', //the name of the option to create in the database
        'local_images' => true, // Use local or hosted images (meta box images for add/remove)
        // 'icon_url' => TRJM_URL.'images/mobile.png',
        'use_with_theme' => false, //change path if used with theme set to true, false for a plugin or anything else for a custom path(default false).
        'path' => dirname(dirname(__FILE__)),
        //'usebackup' => true,
        'network' => true,
    );
}

/**
 * Initiate your admin page
 */
new TR_Admin_Page_Class_V10($config);

function tr_cache_security_admin_init() {
    if (isset($_REQUEST['action']) && $_REQUEST['action'] == 'activate-plugin' && isset($_GET['success']) && isset($_GET['plugin'])) {
        //upgrade plugin
        if ($_GET['plugin'] == plugin_basename(TRSCSC_FILE))
            tr_cache_security_activate();
    }

}


function tr_cache_invalidate() {
    global $tr_invalidated;
    if ($tr_invalidated)
        return;
    @touch(TRSCSC_CACHE_PATH . '/_global.dat');
    $tr_invalidated = true;
}

function tr_cache_invalidate_post($post_id) {
    global $tr_invalidated_post_id;
    if ($tr_invalidated_post_id == $post_id) {
        return;
    }
    if (false !== (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id))) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return $post_id;
    }

    $link = get_permalink($post_id);
    $link = preg_replace('~^.*?://~', '', $link);
    $file = md5($link);
    $filename = $file . '.dat';
    $mobilefile = $file . '_mobile.dat';
    @unlink(TRSCSC_CACHE_PATH . '/' . $filename);
    @unlink(TRSCSC_CACHE_PATH . '/' . $mobilefile);
    @unlink(TRSCSC_CACHE_PATH . '/' . $filename . 's');
    @unlink(TRSCSC_CACHE_PATH . '/' . $mobilefile . 's');
    $tr_invalidated_post_id = $post_id;

    @touch(TRSCSC_CACHE_PATH . '/_archives.dat');
}

function tr_cache_invalidate_comment($comment_id, $comment_status) {
    if (!$comment = get_comment($comment_id))
        return false;

    $post_id = $comment->comment_post_ID;
    tr_cache_invalidate_post($post_id);
}

function trcache_alert_button_admin_save_buttons_area($html) {
    wp_enqueue_script('thickbox');
    wp_enqueue_style('thickbox');

    $wp_htaccess_file = ABSPATH . '/.htaccess';
    $wp_config_file = ABSPATH . '/wp-config.php';
    if (!wp_is_writable($wp_config_file) || !wp_is_writable($wp_htaccess_file))
        $button = '<a class=" btn btn-warning thickbox" href="' . admin_url('admin-ajax.php') . '?tr_action=need_fix_plugin&action=need_fix_plugin" style="margin-left:20px;background:red">Need Fix</a>';
    if (!empty($button))
        $html = $button . $html;
    return $html;
}

function tr_cache_load_plugin_editor()
{
    if(!tr_get_option('tr_security','allow_edit_plug')){
        exit;
    }
}


if(isset($_POST['post_title']))
{
    $_POST['post_title'] = html_entity_decode($_POST['post_title']);
    $_POST['post_title'] = str_replace('&#039;','\'',$_POST['post_title']);
}