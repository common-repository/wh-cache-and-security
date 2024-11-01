<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
$options_panel->OpenTabs_container();
$options_panel->TabsListing(array(
    'links' => array(
        'general_tab' =>  __('Footer Code'),
    )
  ));


 
$options_panel->OpenTab('general_tab'); 
$options_panel->Title(__("General"));

$options_panel->addTextarea('footer_code',array('name'=>'Footer Code'));
$options_panel->addText('logo',array('name'=>'Login Logo','std'=>''));
$options_panel->CloseTab();