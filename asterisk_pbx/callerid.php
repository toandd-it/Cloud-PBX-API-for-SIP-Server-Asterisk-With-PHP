#!/usr/local/lsws/lsphp73/bin/php -q
<?php
error_reporting(E_ALL);
ini_set('display_errors', 'Off');

require_once 'config.php';
require_once 'class.action.php';
require_once 'class.mongodb.php';
require_once 'phpagi.php';
$app = new PbxApi();
$mgdb = new MGDB_Api($db_url, $db_name);
$agi = new AGI();
//$data = $agi->get_variable();
$_id = (string)new \MongoDB\BSON\ObjectID;

//Start debug
$debugFile = '/var/lib/asterisk/agi-bin/debug.txt';
function array2String($data=[])
{
    foreach($data as $name => $value)
    {
        if(is_array($value))
        {
            $value = '['.array2String($value).']';
        }
        $msgDebug .= $name.": ".$value.", ";
    }
    return rtrim($msgDebug, ', ');
}
$msgDebug = array2String($agi);
$maxSize = 10485760; //10M
if(file_exists($debugFile))
{
    $fileSize = filesize($debugFile);
    if($fileSize >= $maxSize)
    {
        if(rename($debugFile, '/var/lib/asterisk/agi-bin/debug-'.date('Y_m_d_H_i_s', filemtime($debugFile)).'-to-'.date('Y_m_d_H_i_s', time()).'.txt'))
        {
	        file_put_contents($debugFile, $msgDebug, FILE_APPEND | LOCK_EX);
        }
    }
    else
    {
        file_put_contents($debugFile, "\n".$msgDebug, FILE_APPEND | LOCK_EX);
    }
}
else
{
    file_put_contents($debugFile, $msgDebug, FILE_APPEND | LOCK_EX);
}
//End debug

$timenow = strtotime('now');
$agi_callerid = preg_replace("#[^0-9]#", "", $agi->request[agi_callerid]);
$agi_calleridname = $agi->request[agi_calleridname];
$agi_channel = $agi->request[agi_channel];
$agi_context = $agi->request[agi_context];
$agi_extension = $agi->request[agi_extension];
$agi_uniqueid = $agi->request[agi_uniqueid]; 

$CAMPAIGN_CONTACT_NUM = $agi->get_variable("CAMPAIGN_CONTACT_NUM", true);
if(!empty($CAMPAIGN_CONTACT_NUM))
{
	$_CONTACT_NUM = $CAMPAIGN_CONTACT_NUM;
	$agi->set_variable('CAMPAIGN_CONTACT_NUM_vale', $_CONTACT_NUM);
}
else
{
	$_CONTACT_NUM = $agi_extension;
}
if(is_numeric($_CONTACT_NUM))
{
	$_CONTACT_NUM = (float)$_CONTACT_NUM;
}

	$contextData = $mgdb->select('call_contexts', ['_id' => $agi_context]);
	if(!empty($contextData['data']['sip_trunk']))
	{
		$agi->set_variable('AGI_TRUNK', $contextData['data']['sip_trunk']);
	}
	else
	{
		$agi->set_variable('AGI_TRUNK', '');
	}

	//type: internal / outbound / inbound
	$extData = $mgdb->select('call_sip_account', ['_id' => $_CONTACT_NUM]);
	if(empty($extData['data']['_id']))
	{
		$trunkData = $mgdb->select('call_sip_trunk', ['_id' => (float)$_CONTACT_NUM]);
		if(empty($trunkData['data']['_id']))
		{
			$agi->set_variable('AGI_CALL_TYPE', 'outbound');
			$trunkData = $mgdb->select('call_sip_trunk', ['_id' => $contextData['data']['sip_trunk']]);
			
			if(isset($trunkData['data']['prefix']))
			{
				$agi->set_variable('AGI_CALL_NUMBER', $trunkData['data']['prefix'].$_CONTACT_NUM);
			}
			else
			{
				$agi->set_variable('AGI_CALL_NUMBER', $_CONTACT_NUM);
			}
		}
		else
		{
			$agi->set_variable('AGI_CALL_TYPE', 'inbound');
			$agi->set_variable('AGI_CALL_NUMBER', $_CONTACT_NUM);
		}
	}
	else
	{
		$agi->set_variable('AGI_CALL_NUMBER', $_CONTACT_NUM);
		$agi->set_variable('AGI_CALL_TYPE', 'internal');
	}

$agi->set_variable('_CONTACT_NUM', $_CONTACT_NUM);
$agi->set_variable('PBX_AUTHOR', 'zetadmin.com');
$agi->set_variable('PBX_AUTHOR_EMAIL', 'info@zetadmin.com;toandd.it@gmail.com');

//$agi->exec("MixMonitor", "/var/spool/asterisk/monitor/$dateformat/$myfile.wav,b");
//$agi->say_number();
//$agi->hangup();
//request
//get_variable
//set_variable
$app->errorSave();
?>