<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

$logo = tr_get_option('trcs_otherst','logo');
if(!empty($logo)) {
    if(stripos($logo,'wp-content/')>0)
    {
        list($m,$logo_path) = explode('wp-content',$logo);
        $logo_path = WP_CONTENT_DIR.$logo_path;
    }
    if(isset($logo_path))
    {
        list($width, $height, $type, $attr) = @getimagesize( $logo_path );
        $new_height = 320 * $height/ $width;
    }
    ?>
    <style>
        body.login h1 a{
            background:url(<?php echo $logo?>) no-repeat center center !important;
            width:320px!important;
        <?php if(isset($new_height)):?>
            height:<?php echo $new_height?>px!important;
        <?php endif;?>
            -webkit-background-size: cover!important;
            background-size: cover!important;
            padding:0 !important;
        }
    </style>
    <?php
}
