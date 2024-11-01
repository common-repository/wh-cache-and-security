<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

$tabs = array(
    'mail' => 'Test Mail',
);
$tab = isset($_REQUEST['tab'])? $_REQUEST['tab']: key($tabs);
if(!isset($tabs[$tab]))
{
    $tab = key($tabs);
}
?>
    <h2 class="nav-tab-wrapper">
        <?php
        foreach($tabs as $k => $txt){
            $class = $tab == $k ? 'nav-tab-active':'';
            $link = Tr_Base_Class_V4::link(array('tab'=>$k,'act'=>'','id'=>''),false);
            echo '<a class="nav-tab '.$class.'" href="'.$link.'">'.$txt.'</a>';
        }
        ?>
    </h2>
<?php
if($tab=='mail')
{
    if(isset($_POST['fields'])){

        add_action('wp_mail_failed','trcs_status_wp_mail_failed');
        function trcs_status_wp_mail_failed($rs)
        {
            var_dump($rs);
        }
        extract($_POST['fields']);

        $rs = wp_mail($to,$subject,$message);
        var_dump($rs);
    }
    ?>
    <form method="post">
        <div class="row">
            <label>Subject</label>
            <input type="text" name="fields[subject]" value="<?php echo $fields['subject']?>"/>
        </div>
        <div class="row">
            <label>To</label>
            <input type="text" name="fields[to]" value="<?php echo $fields['to']?>"/>
        </div>
        <div class="row">
            <label>Message</label>
            <textarea name="fields[message]"><?php echo $fields['message']?></textarea>
        </div>
        <div class="row">
            <input type="submit" class="button button-primary"value="Send"/>
        </div>
    </form>
<?php
}