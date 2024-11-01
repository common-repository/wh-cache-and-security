<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if (!session_id())
    session_start();
wp_enqueue_script('jquery-ui-core');
wp_enqueue_script('jquery-ui-progressbar');

$_SESSION['tr_log_scan'] = array();
$scan = new Tr_Scan_Dir();
$dir = dirname(WP_CONTENT_DIR);
//$count_folders = $scan->count_dir($dir);
?>

<link rel='stylesheet' id='mg-colpick-css'  href='<?php echo TRSCSC_URL ?>css/admin.css' type='text/css' media='all' />
<div class="scan_wraper">
    <div id="status_bar">
        <div class="progress-label">0%</div>
        <div class="left"></div>
        <div class="right">
            <div><?php echo $count_folders ?> Folders Remaining</div>
        </div>
    </div>
    <div id="scan_result"></div>
</div>
<script>
    jQuery(function ($) {
        var $progressbar = $("#status_bar");
        var $progressLabel = $( ".progress-label" );
        $progressbar.progressbar({
            value: false,
            change: function () {
                $progressLabel.text( $progressbar.progressbar( "value" ) + "%" );
            },
            complete: function () {
                $progressLabel.text( $progressbar.progressbar( "value" ) + "%" );
            }
        });
        $progressbar.progressbar("value", 33);
        function do_scan_sc()
        {
            $.ajax({
                url: ajaxurl,
                type: 'post',
                dataType: 'json',
                data: {action: 'ajax', tr_action: 'do_scan_quick_sc'},
                success: function (rs)
                {
                    $('#scan_result').append(rs.log);
                    if (rs.status != 'done')
                    {
                        $progressbar.progressbar("value", rs.value);
                        do_scan_sc();
                    } else
                    {
                        $('#loading_scan').hide();
                    }
                }
            });
        }
        do_scan_sc();
    })
</script>