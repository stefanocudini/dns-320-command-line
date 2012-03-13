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
simplehtmldom (http://simplehtmldom.sourceforge.net)
*/
$options = array('H:' =>'host:',
				 'p::'=>'p2p::',
				 't' =>'temp',
 				 'u' =>'ups',
				 'd' =>'disks',
				 's' =>'shutdown',
				 'r' =>'restart',				 				 
 				 'h' =>'help');
define('HELP',
	"Usage: pulse.php [OPTIONS]\n".
	"       -H,--host         Hostname or ip target, where sharecenter dns-320\n".
	"       -p,--p2p[=on|off] get or set p2p client state\n".
	"       -t,--temp         get temperature inside\n".
	"       -u,--ups          get ups state\n".
	"       -d,--disks        get disks usage\n".
	"       -s,--shutdown     power off the system\n".
	"       -r,--restart      restart the system\n".
	"       -h,--help         print this help\n\n");
$opts = getopt(implode('',array_keys($options)),array_values($options));
print_r($opts);
#exit(0);

if(count($opts)==0)
	help();

if(isset($opts['H']))
	define('HOST', $opts['H']);
	
elseif(isset($opts['host']))
	define('HOST', $opts['host']);
	
else
	define('HOST', 'pulse');

define('USER','admin');
define('PASS','admin');

define('CJAR', basename(__FILE__).'_cookies.txt');
define('UAGENT', isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : "Mozilla/5.0 (Windows; U; Windows NT 5.1; it-it; rv:1.8.1.3) Gecko/20070309 Firefox/3.0.0.6");

$urls['login'] = "http://".HOST."/cgi-bin/login_mgr.cgi";
$params['loginSet'] = "cmd=login&username=".USER."&pwd=".PASS."&port=&f_type=1&f_username=&pre_pwd=admin&C1=ON&ssl_port=443";

$urls['stat'] = "http://".HOST."/cgi-bin/status_mgr.cgi";
$params['statGetStatus'] = array('cmd'=>'cgi_get_status');

$urls['disk'] = "http://".HOST."/cgi-bin/dsk_mgr.cgi";
$params['diskStatus'] = array('cmd'=>'Status_HDInfo');

$urls['sys'] = "http://".HOST."/cgi-bin/system_mgr.cgi";
$params['sysRestart'] = array('cmd'=>'cgi_restart');
$params['sysShutdown'] = array('cmd'=>'cgi_shutdown');

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
$params['p2pGetConfig'] = array('cmd'=>'p2p_get_setting_info');
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

foreach($opts as $opt=>$optval)
{
	switch($opt)
	{
		case 'p':
		case 'p2p':
			switch($optval)
			{
				case 'on':
					p2pSetConfig( array('on'=>true) );
				break;
				case 'off':
					p2pSetConfig( array('on'=>false) );
				break;
			}
			$p2pConf = p2pGetConfig();
			echo "P2P:\t".((bool)$p2pConf['p2p'] ? 'on':'off');
		break;
/*		case 'down':
			if(isset($argv[3]))
				p2pSetConfig( array('down'=>intval($argv[3])) );
			$p2pConf = p2pGetConfig();
			echo " down: ".$p2pConf['bandwidth_downlaod_rate']."\n";
		break;
		case 'up':
			if(isset($argv[3]))
				p2pSetConfig( array('up'=>intval($argv[3])) );
			$p2pConf = p2pGetConfig();
			echo " up:   ".$p2pConf['bandwidth_upload_rate']."\n";
		break;
*/
		case 'u':
		case 'ups':
			$u = upsGetInfo();
			echo "UPS:\t".($u ? $u['stat']."\n ".' battery: '.$u['bat'] : 'off');
		break;

		case 't':
		case 'temp':
			echo "TEMP:\t".sysGetTemp();
		break;

		case 'd':
		case 'disks':
			$d = diskGetInfo();
			#var_export($d);
			echo "DISKS:\t".count($d['Volume'])."\n";
			foreach($d['Volume'] as $disk)
			{
				$disk['free_size'] = $disk['total_size'] - $disk['used_size'];
				echo " ".$disk['shared_name'].': '.bytesConvert($disk['total_size']*1000)."\n".
 					 "   free: ".bytesConvert($disk['free_size']*1000)."\n".
					 "   used: ".$disk['used_rate']."\n\n";
			}
		break;

		case 's':
		case 'shutdown':
			echo "Shutdown system...";
			sysShutdown();
		break;
		case 'r':
		case 'restart':
			echo "Restart system...";
			sysRestart();
		break;
		
		case 'h':
		case 'help':
		default:
			help();
	}
	echo "\n";
}

function help()
{
	die(HELP);
}

function sysRestart()
{
	global $urls;
	global $params;
	http_post_request($urls['sys'],$params['sysRestart']);
}

function sysShutdown()
{
	global $urls;
	global $params;
	http_post_request($urls['sys'],$params['sysShutdown']);
}

function upsGetInfo()
{
	global $urls;
	global $params;
	$ups = xml2array( http_post_request($urls['stat'],$params['statGetStatus']) );
	$upsret = ($ups['usb_type']=='UPS') ? array('bat'=>$ups['battery'],'stat'=>$ups['ups_status']) : false;
	return $upsret;
}

function sysGetTemp()
{
	global $urls;
	global $params;	
	$sys = xml2array( http_post_request($urls['stat'],$params['statGetStatus']) );
	$t = next( explode(':',$sys['temperature']) );
	return $t;
}

function diskGetInfo()
{
	global $urls;
	global $params;
	$sys = xml2array( http_post_request($urls['disk'],$params['diskStatus']) );
	#$t = next( explode(':',$sys['temperature']) );
	return $sys;
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
		
	require_once('simple_html_dom.php');	//http://simplehtmldom.sourceforge.net
	$html = str_get_html( http_post_request($urls['login'],$params['loginSet']) );
	$ldiv = $html->find("div[id=login]");
	
	return !(is_array($ldiv) and count($ldiv)>0);
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
#	curl_setopt($ch, CURLOPT_HTTPHEADER, array("Connection: close"));	
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

function bytesConvert($bytes)
{
    $ext = array('B', 'kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
    $unitCount = 0;
    for(; $bytes > 1024; $unitCount++) $bytes /= 1024;
    return round($bytes,2).$ext[$unitCount];
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
