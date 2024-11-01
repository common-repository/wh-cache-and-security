<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

$current_offset = get_option( 'gmt_offset' ) * HOUR_IN_SECONDS;
$cron = _get_cron_array();
$schedules = wp_get_schedules();
$date_format = _x( 'M j, Y @ G:i', 'Publish box date format', 'cron-view' );
foreach ( $cron as $timestamp => $cronhooks ) {
    foreach ( (array) $cronhooks as $hook => $events ) {
        foreach ( (array) $events as $key => $event ) {
            $cron[ $timestamp ][ $hook ][ $key ][ 'date' ] = date_i18n( $date_format, $timestamp );
        }
    }
}
?>

<div class="wrap" id="cron-gui">
    <div id="icon-tools" class="icon32"><br /></div>
    <h2><?php _e( 'What\'s in Cron?', 'cron-view' ); ?></h2>


    <h3><?php _e('Available schedules', 'cron-view'); ?></h3>

    <ul>
        <?php foreach( $schedules as $schedule ) { ?>
            <li><strong><?php echo $schedule[ 'display' ]; ?></strong>, every <?php echo human_time_diff( 0, $schedule[ 'interval' ] ); ?></li>
        <?php } ?>
    </ul>

    <h3><?php _e('Events', 'cron-view'); ?></h3>

    <table class="widefat fixed">
        <thead>
        <tr>
            <th scope="col"><?php _e('Next due (GMT/UTC)', 'cron-view'); ?></th>
            <th scope="col">Local date</th>
            <th scope="col"><?php _e('Schedule', 'cron-view'); ?></th>
            <th scope="col"><?php _e('Hook', 'cron-view'); ?></th>
            <th scope="col"><?php _e('Arguments', 'cron-view'); ?></th>
        </tr>
        </thead>
        <tbody>
        <?php
        $count = 0;
        foreach ( $cron as $timestamp => $cronhooks ) { ?>
            <?php foreach ( (array) $cronhooks as $hook => $events ) { ?>
                <?php foreach ( (array) $events as $event ) {
                    $count++;
                    ?>
                    <tr>
                        <th scope="row"><?php echo $event[ 'date' ]; ?> (<?php echo $timestamp; ?>)</th>
                        <td><?php echo date('Y-m-d H:i:s',$timestamp+$current_offset)?></td>
                        <td>
                            <?php
                            if ( $event[ 'schedule' ] ) {
                                echo $schedules [ $event[ 'schedule' ] ][ 'display' ];
                            } else {
                                ?><em><?php _e('One-off event', 'cron-view'); ?></em><?php
                            }
                            ?>
                        </td>
                        <td><?php echo $hook; ?></td>
                        <td><?php if ( count( $event[ 'args' ] ) ) { ?>
                                <ul>
                                    <?php foreach( $event[ 'args' ] as $key => $value ) { ?>
                                        <strong>[<?php echo $key; ?>]:</strong> <?php echo $value; ?>
                                    <?php } ?>
                                </ul>
                            <?php } ?></td>
                    </tr>
                <?php } ?>
            <?php } ?>
        <?php } ?>
        </tbody>
    </table>
    <div>Total: <?php echo $count?></div>
    <div>
        Current: <?php echo date('Y-m-d H:i:s',time())?>
    </div>
    <div>
        Local: <?php echo date('Y-m-d H:i:s',time()+ $current_offset)?>
    </div>

</div>
