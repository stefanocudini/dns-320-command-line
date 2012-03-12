#!/usr/bin/env php
<?
#header("Content-type: text/plain");

/*
SIMPLE COMMAND-LINE INTERFACE for D-LINK SHARECENTER DNS-320
Copyleft Stefano Cudini 2012
stefano.cudini@gmail.com

requirements:
php5-cli
php5-curl
http://simplehtmldom.sourceforge.net/

Usage:
	$./pulse.php [p2p [on|off]]

*/

define('HOST','pulse');//ip or hostname of youre sharecenter dns-320
define('USER','admin');
define('PASS','admin');
define('CJAR', basename(__FILE__).'_cookies.txt');
define('UAGENT', isset($_SERVER['HTTP_USER_AGENT'])?$_SERVER['HTTP_USER_AGENT']:"Mozilla/5.0 (Windows; U; Windows NT 5.1; it-it; rv:1.8.1.3) Gecko/20070309 Firefox/3.0.0.6");

$urls['login'] = "http://".HOST."/cgi-bin/login_mgr.cgi";
$params['loginSet'] = "cmd=login&username=".USER."&pwd=".PASS."&port=&f_type=1&f_username=&pre_pwd=admin&C1=ON&ssl_port=443";

$urls['p2p'] = "http://".HOST."/cgi-bin/p2p.cgi";
$params['p2pStatus'] = array(
'cmd'=>'p2p_get_list_by_priority',
'page'=>1,
'rp'=>10,
'sortname'=>'undefined',
'sortorder'=>'undefined',
'query'=>'',
'qtype'=>'',
'f_field'=>0
);
$params['p2pGetConfig'] = array(
'cmd'=>'p2p_get_setting_info'
);
$params['p2pSetConfig'] = array(
'f_P2P'=>1,
'f_auto_download'=>1,
'f_port_custom'=>true,
'f_seed_type'=>0,
'f_encryption'=>1,
'f_flow_control_schedule_max_download_rate'=>-1,
'f_flow_control_schedule_max_upload_rate'=>-1,
'f_bandwidth_auto'=>false,
'f_flow_control_schedule'=>'111111111112221111112222111111111112221111112222111111111112221111112222111111111112221111112222111111111112221111112222111111111112221111112222111111111112221111112222',
'cmd'=>'p2p_set_config',
'tmp_p2p_state'=>''
);


login() or die("ERROR LOGIN\n");

if(isset($argv[1]))
{
	switch($argv[1])
	{
		case 'p2p':
			$p2pConf = p2pGetConfig();
			
			if(isset($argv[2]))
				switch($argv[2])
				{
					case 'on':
						p2pSetConfig( array('on'=>true) );
					break;
					case 'off':
						p2pSetConfig( array('on'=>false) );
					break;
					case 'down':
						if(isset($argv[3]))
							p2pSetConfig( array('down'=>intval($argv[3])) );
					break;
/*					case 'up':
						if(isset($argv[3]))
							p2pSetConfig( array('up'=>intval($argv[3])) );
					break;*/
				}
			$p2pConf = p2pGetConfig();
			echo "P2P:   ".((bool)$p2pConf['p2p'] ? 'on':'off')."\n";				
			echo " down: ".$p2pConf['bandwidth_downlaod_rate']."\n";
#			echo " up:   ".$p2pConf['bandwidth_upload_rate']."\n";
		break;

		case 'ups':
			$ups = upsGetInfo();
			echo 'UPS: '.($ups ? $ups['stat']."\n ".' battery: '.$ups['bat'] : 'off');
		break;

		case 'temp':
			echo sysGetTemp();
		break;
	}
	echo "\n";
}
else
	help();


//	DEFINIZIONE FUNZIONI

function help()
{
	die(
	"Usage:\n".
		"\t$./pulse.php [p2p [on|off] | [down|up <KBps>] ]\n".
	"\n"
	);
}

function upsGetInfo()
{
	global $urls;
	global $params;
	
	$ups = xml2array( http_post_request("http://pulse/cgi-bin/status_mgr.cgi",array('cmd'=>'cgi_get_status')) );

	if($ups['usb_type']=='UPS')
		$upsret = array('bat'=>$ups['battery'],'stat'=>$ups['ups_status']);
	else
		return false;

	return $upsret;
}

function sysGetTemp()
{
	$sys = xml2array( http_post_request("http://pulse/cgi-bin/status_mgr.cgi",array('cmd'=>'cgi_get_status')) );
	$t = next( explode(':',$sys['temperature']) );
	return $t;
}

function p2pGetConfig()
{
	global $urls;
	global $params;
	return xml2array( http_post_request($urls['p2p'],$params['p2pGetConfig']) );
}

function p2pSetConfig($sets=array())
{
	global $urls;
	global $params;

	if(isset($sets['on']))   $params['p2pSetConfig']['f_P2P']= $sets['on'] ? 1:0;
	if(isset($sets['down'])) $params['p2pSetConfig']['f_flow_control_schedule_max_download_rate']= $sets['down'];
	if(isset($sets['up']))   $params['p2pSetConfig']['f_flow_control_schedule_max_upload_rate']= $sets['up'];
	
	return xml2array( http_post_request($urls['p2p'],$params['p2pSetConfig']) );//XMLObj to Array
}

function login()
{
	global $urls;
	global $params;
	static $logged = false;
	
	if($logged) return false;
	
	require_once('simple_html_dom.php');	//http://simplehtmldom.sourceforge.net
	
	$html = str_get_html( http_post_request($urls['login'],$params['loginSet']) );
	$ldiv = $html->find("div[id=login]");
	$logged = !(is_array($ldiv) and count($ldiv)>0);
	return $logged;
}

function http_get_request($url)
{
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url );
	curl_setopt($ch, CURLOPT_POST, 0);
	curl_setopt($ch, CURLOPT_HEADER, 0);//non mostra header ricevuto
	curl_setopt($ch, CURLOPT_HTTPHEADER, array("Connection: keep-alive","Keep-Alive: 300"));
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_COOKIEJAR, CJAR);
	curl_setopt($ch, CURLOPT_COOKIEFILE, CJAR);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_USERAGENT, UAGENT);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
	$a = curl_exec($ch);
	curl_close($ch);
	return $a;
}

function http_post_request($url,$pdata)
{
	$pdata = is_array($pdata) ? http_build_query($pdata) : $pdata;
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url );
	curl_setopt($ch, CURLOPT_POSTFIELDS, $pdata);
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_COOKIEJAR, CJAR);
	curl_setopt($ch, CURLOPT_COOKIEFILE, CJAR);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.1; it-it; rv:1.8.1.3) Gecko/20070309 Firefox/3.0.0.6");
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
	$a = curl_exec($ch);
	curl_close($ch);
	return $a;
}

function xml2array($xml) 
{ 
	return json_decode(json_encode(simplexml_load_string($xml)),true);
}

function json_indent($json)
{
    $result    = '';
    $pos       = 0;
    $strLen    = strlen($json);
    $indentStr = '  ';
    $newLine   = "\n";
    for($i = 0; $i <= $strLen; $i++)
    {
        $char = substr($json, $i, 1);
        if($char == '}' || $char == ']') {
            $result .= $newLine;
            $pos --;
            for($j=0; $j<$pos; $j++)
                $result .= $indentStr;
        }
        $result .= $char;
        if($char == ',' || $char == '{' || $char == '[') {
            $result .= $newLine;
            if($char == '{' || $char == '[')
                $pos ++;
            for($j = 0; $j<$pos; $j++)
                $result .= $indentStr;
        }
    }
    return $result;
}

function xml_indent($xml)
{    
  // add marker linefeeds to aid the pretty-tokeniser (adds a linefeed between all tag-end boundaries)
  $xml = preg_replace('/(>)(<)(\/*)/', "$1\n$2$3", $xml);
  
  // now indent the tags
  $token      = strtok($xml, "\n");
  $result     = ''; // holds formatted version as it is built
  $pad        = 0; // initial indent
  $matches    = array(); // returns from preg_matches()
  
  // scan each line and adjust indent based on opening/closing tags
  while ($token !== false) : 
  
    // test for the various tag states
    
    // 1. open and closing tags on same line - no change
    if (preg_match('/.+<\/\w[^>]*>$/', $token, $matches)) : 
      $indent=0;
    // 2. closing tag - outdent now
    elseif (preg_match('/^<\/\w/', $token, $matches)) :
      $pad--;
    // 3. opening tag - don't pad this one, only subsequent tags
    elseif (preg_match('/^<\w[^>]*[^\/]>.*$/', $token, $matches)) :
      $indent=1;
    // 4. no indentation needed
    else :
      $indent = 0; 
    endif;
    
    // pad the line with the required number of leading spaces
    $line    = str_pad($token, strlen($token)+$pad, ' ', STR_PAD_LEFT);
    $result .= $line . "\n"; // add to the cumulative result, with linefeed
    $token   = strtok("\n"); // get the next token
    $pad    += $indent; // update the pad size for subsequent lines    
  endwhile; 
  
  return $result;
}

?>
