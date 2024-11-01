<?php
define('ABSPATH',dirname(__FILE__));
//this file need run by itselft

if($_GET['s']=='img')
{
    require(ABSPATH."/inc/phpcaptcha.php");

    $aFonts = array(ABSPATH.'/font/VeraBd.ttf');
    
    $oVisualCaptcha = new PhpCaptcha($aFonts, 140, 25);
    
    $oVisualCaptcha->UseColour(true);	 
    
    $oVisualCaptcha->SetNumChars(4);
    
    $oVisualCaptcha->SetMinFontSize(16);
    
    $oVisualCaptcha->SetMaxFontSize(18);
    
    $oVisualCaptcha->Create();
}
