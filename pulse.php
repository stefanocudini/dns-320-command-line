#!/usr/bin/env php
<?
/*
SIMPLE COMMAND-LINE INTERFACE for D-LINK SHARECENTER DNS-320
Copyleft Stefano Cudini 2012
stefano.cudini@gmail.com

requirements:
php5-cli
php5-curl
*/

define('DEBUG', true);

define('HELP',"
Usage: pulse.php OPTIONS [host[:port]]
       host                    hostname or ip target, default: pulse
       port                    port number for host, default: 80
       -p,--p2p[=on|off]       get or set p2p client state
       -c,--p2p-clear          clear p2p complete list
       -D,--download[=url]     list or add url in http downloader
       -C,--download-clear     clear complete http downloads list
       -t,--temp               get temperature inside
       -T,--time               get date and time of nas
       -f,--fan=[off|low|high] get or set fan mode
       -u,--ups                get ups state
       -d,--disks              get disks usage
       -s,--shutdown           power off the system
       -r,--restart            restart the system
       -h,--help               print this help

");

$options = array(
		'p::'=> 'p2p::',
		'c'  => 'p2p-clear',
		'D::'=> 'download::',
		'C'  => 'download-clear',
		't'  => 'temp',
		'T'  => 'time',		
		'f::'=> 'fan::',
		'u'  => 'ups',
		'd'  => 'disks',
		's'  => 'shutdown',
		'r'  => 'restart',				 				 
		'h'  => 'help');

if(version_compare(PHP_VERSION, '5.3.0', '<'))
	$opts = getopt(implode('',array_keys($options)));
else
	$opts = getopt(implode('',array_keys($options)),array_values($options));

debug(print_r($opts,true));
debug(print_r($argv,true));

$hostport = array_pop($argv);
if($argc>1 and $hostport{0}!='-')//if not a option
{
	if(!checkurl('http://'.$hostport.'/'))
		die("ERROR HOST\n");
	define('HOST', $hostport);
}
else
	define('HOST', 'pulse');


if(count($opts)==0)
	help();

define('USER','admin');
define('PASS','admin');

define('DOWNDIR','Volume_1');//target path inside nas for http download

define('CJAR', '_cookies.txt');
define('UAGENT', isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : "Mozilla/5.0 (Windows; U; Windows NT 5.1; it-it; rv:1.8.1.3) Gecko/20070309 Firefox/3.0.0.6");

define('BASEURL', 'http://'.HOST.'/cgi-bin/');

$urls['login'] = BASEURL.'login_mgr.cgi';
$params['loginSet'] = array(
	'cmd'=>'login',
	'username'=>USER,
	'pwd'=>PASS,
	'port'=>'',
	'f_type'=>1,
	'f_username'=>'',
	'pre_pwd'=>USER,
	'C1'=>'ON',
	'ssl_port'=>443
);

$urls['stat'] = BASEURL.'status_mgr.cgi';
$params['statGetStatus'] = array('cmd'=>'cgi_get_status');

$urls['disk'] = BASEURL.'dsk_mgr.cgi';
$params['diskStatus'] = array('cmd'=>'Status_HDInfo');

$urls['sys'] = BASEURL.'system_mgr.cgi';
$params['sysRestart'] = array('cmd'=>'cgi_restart');
$params['sysShutdown'] = array('cmd'=>'cgi_shutdown');
$params['sysGetFan'] = array('cmd'=>'cgi_get_power_mgr_xml');
$params['sysSetFan'] = array(
	'cmd'=>'cgi_fan',
	'f_fan_type'=>0
);
$params['sysGetTime'] = array('cmd'=>'cgi_get_time');

$urls['p2p'] = BASEURL.'p2p.cgi';
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
$params['p2pGetList'] = array(
	'cmd'=>'p2p_get_list_by_priority',
	'page'=>1,
	'rp'=>20,
	'sortname'=>'undefined',
	'sortorder'=>'undefined',
	'query'=>'',
	'qtype'=>'',
	'f_field'=>0
);
$params['p2pClearList'] = array('cmd'=>'p2p_del_all_completed');

$urls['down'] = BASEURL.'download_mgr.cgi';
$params['downAddUrl'] = array(
	'f_downloadtype'=>0,
	'f_login_method'=>1,
	'f_type'=>0,
	'f_URL'=>'http://host/path/file',
	'f_dir'=>DOWNDIR,
	'f_rename'=>'',
	'f_lang'=>'UTF-8',
	'f_default_lang'=>'none',
	'f_date'=>'',
	'f_hour'=>21,
	'f_min'=>27,
	'f_period'=>'none',
	'f_at'=>201203132127,
	'f_idx'=>'',
	'f_login_user'=>USER,
	'cmd'=>'Downloads_Schedule_Add',
	'rp'=>10
);
$params['downDelUrl'] = array(
	'cmd'=>'Downloads_Schedule_Del',
	'f_idx'=>'',
	'f_field'=>USER
);
$params['downGetList'] = array(
	'cmd'=>'Downloads_Schedule_List',
	'page'=>1,
	'rp'=>10,
	'sortname'=>'undefined',
	'sortorder'=>'undefined',
	'query'=>'',
	'qtype'=>'',
	'f_field'=>USER
);

//start

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
	
			if((bool)$p2pConf['p2p'])
				p2pPrintList();
		break;
		case 'c':
		case 'p2p-clear':
			$p2pConf = p2pGetConfig();		
			if((bool)$p2pConf['p2p'])
			{
				p2pClearList();
				p2pPrintList();
			}
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
		case 'D':
		case 'download':
			if(!empty($optval))
				downAddUrl($optval);
			downPrintList();
		break;

		case 'C':
		case 'download-clear':
			$dd = downGetList();
			foreach($dd as $d)
				if($d['status']=='complete')
					downDelUrl($d['id']);
			downPrintList();
		break;

		case 'u':
		case 'ups':
			$u = upsGetInfo();
			echo "UPS:\t".($u ? $u['stat']."\n ".' battery: '.$u['bat'] : 'off');
		break;

		case 't':
		case 'temp':
			echo "TEMP:\t".sysGetTemp();
		break;

		case 'T':
		case 'time':
			echo "TIME:\t".sysGetTime();
		break;
		
		case 'f':
		case 'fan':
			switch($optval)
			{
				case 'off':	 //0: Auto (off/low/high)
					sysSetFan(0);
				break;
				case 'low':	 //1: Auto (low/high)
					sysSetFan(1);
				break;
				case 'high': //2: Manual (always high)
					sysSetFan(2);
				break;
			}
			echo "FAN:\t".sysGetFan();
		break;
		
		case 'd':
		case 'disks':
			$d = diskGetInfo();
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
			if(!confirm("Are you sure you want to poweroff NAS now?")) break;
			echo "Shutdown system...";
			sysShutdown();
		break;
		case 'r':
		case 'restart':
			if(!confirm("Are you sure you want to restart NAS now?")) break;
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

//end

function debug($var)
{
	if(DEBUG)
		file_put_contents('php://stderr',$var."\n");
}

function checkurl($url)
{
	return (bool)@file_get_contents($url,0,NULL,0,1);
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

function sysGetTime()
{
	global $urls;
	global $params;	
	$t = xml2array( http_post_request($urls['sys'],$params['sysGetTime']) );
	return $t['day'].'/'.$t['mon'].'/'.$t['year'].', '.$t['hour'].':'.$t['min'].':'.$t['sec'];
}

function sysGetFan()
{
	global $urls;
	global $params;	
	$sys = xml2array( http_post_request($urls['sys'],$params['sysGetFan']) );
	$ff = array('off','low','high');
	$f = $ff[ $sys['fan'] ];
	return $f;
}

function sysSetFan($mode)
{
	global $urls;
	global $params;
	$params['sysSetFan']['f_fan_type'] = $mode;
	http_post_request($urls['sys'],$params['sysSetFan']);
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

function p2pGetList()
{
	global $urls;
	global $params;

	$jj = http_post_request($urls['p2p'],$params['p2pGetList']);
	$jj = preg_replace('/:\s*\'(([^\']|\\\\\')*)\'\s*([},])/e',
					   "':'.json_encode(stripslashes('$1')).'$3'", $jj);
	$jj = preg_replace("/([,\{])([a-zA-Z0-9_]+?):/" , "$1\"$2\":", $jj);
	//correcting not standard JSON!! fuck!!
	
	$pp = json_decode($jj,true);
	#print_r($pp);
	
	$P = array();
	foreach($pp['rows'] as $pc)
	{
		$p = $pc['cell'];
		if(strstr($p[4],'status_download'))   $s = 'download';
		elseif(strstr($p[4],'status_queue'))  $s = 'stopped';
		elseif(strstr($p[4],'status_upload')) $s = 'complete';
	
		preg_match("/.*>(.*)<.*/", $p[0], $f);//file
		preg_match("/.*>(.*)<.*/", $p[3], $g);//progress
		$P[]= array('progress'=> $g[1].'%',
					'status'=>   $s,
					'speed'=>    $p[5],
					'file'=>     substrStrip($f[1], 60),
					'id'=>       $p[7]);
	}
	return $P;
}

function p2pClearList()
{
	global $urls;
	global $params;
	http_post_request($urls['p2p'],$params['p2pClearList']);
}

function p2pPrintList()
{
	$pp = p2pGetList();	
	echo "\n queue: ".count($pp)."\n";
	foreach($pp as $p)
		echo '  '.$p['status']."\t".$p['progress']."\t".$p['speed']."\t".basename($p['file'])."\n";
}

function downAddUrl($url)
{
	global $urls;
	global $params;

	$params['downAddUrl']['f_URL']= $url;
	$params['downAddUrl']['f_date']= date("m/d/Y");
	$params['downAddUrl']['f_hour']= date("h");
	$params['downAddUrl']['f_min']= date("i");
	//control time zone from dns-320 and where execute script
	http_post_request($urls['down'],$params['downAddUrl']);
}

function downDelUrl($idurl)
{
	global $urls;
	global $params;
	$params['downDelUrl']['f_idx']= $idurl;
	http_post_request($urls['down'],$params['downDelUrl']);
}

function downGetList()
{
	global $urls;
	global $params;
	$L = array();
	$dd = xml2array( http_post_request($urls['down'],$params['downGetList']) );
#	var_export($dd);
#	echo json_indent(json_encode($dd));

	if(!isset($dd['row']))
		return $L;
		
	if(isset($dd['row'][0]))//multiple result
		$rows = $dd['row'];
	else	//only one result
		$rows = array($dd['row']);
	
	foreach($rows as $U)
	{
		$u = $U['cell'];
		if(strstr($u[3],'status_download')) $s = 'download';
		elseif(strstr($u[3],'icon_stop'))   $s = 'stopped';
		elseif(strstr($u[3],'status_ok'))   $s = 'complete';
	
		preg_match("/.*>(.*)<.*/", $u[2], $p);
		$L[]= array('progress'=> $p[1].'%',
					'status'=>   $s,
					'speed'=>    $u[4].'s',
					'url'=>      $u[0],
					'id'=>       $u[8]);
	}
	return $L;
}

function downPrintList()
{
	sleep(1);//useful after downAddUrl()
	$dd = downGetList();
	echo "DOWNLOADS: ".count($dd)."\n";
	foreach($dd as $d)
		echo ' '.$d['status']."\t".$d['progress']."\t".$d['speed']."\t\t".basename($d['url'])."\n";
}
////////////////////////////////////////


function login()
{
	global $urls;
	global $params;

	list($head,$body) = http_post_request($urls['login'], $params['loginSet'], true);
#	debug(print_r($head,true));
//login conditions:
//RESP OK: Location:http://pulse/web/home.html
//         Set-Cookie:username=admin; path=/
//RESP ERROR: Location:http://pulse/web/relogin.html
	return (isset($head['Set-Cookie']) and strstr($head['Set-Cookie'],'username='.USER) );
}

function http_post_request($url, $pdata, $getHeaders=false)
{
	debug("URL: $url");
	
	$pdata = is_array($pdata) ? http_build_query($pdata) : $pdata;
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url );
	curl_setopt($ch, CURLOPT_POSTFIELDS, $pdata);
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_COOKIEJAR, CJAR);
	curl_setopt($ch, CURLOPT_COOKIEFILE, CJAR);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_USERAGENT, UAGENT);
#	curl_setopt($ch, CURLOPT_HTTPHEADER, array("Connection: close"));
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

	if($getHeaders)
		curl_setopt($ch, CURLOPT_HEADER, 1);
	else
		curl_setopt($ch, CURLOPT_HEADER, 0);		

	$resp = curl_exec($ch);
	
	if($getHeaders)
	{
		$info = curl_getinfo($ch);
		$head = array();
		foreach( explode("\r\n",substr($resp,0,$info['header_size'])) as $row )
			$head[ current(explode(': ',$row)) ] = next(explode(': ',$row));
		//split http head in headers key=>value

		$body = substr($resp, -$info['download_content_length']);  	
		$resp =  array($head, $body);
	}
	curl_close($ch);
	return $resp;
}

function confirm($text)
{
	echo "$text [y/n] ";
	return trim(fgets(STDIN))=='y';
}

/*function http_get_request($url)//GET
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
}//*/

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

function substrStrip($string, $length=NULL)
{
	if($length==NULL)
		$length = 50;
	$stringDisplay = substr($string, 0, $length);
	if(strlen($string) > $length)
		$stringDisplay .= '...';
	return $stringDisplay;
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
	$xml = preg_replace('/(>)(<)(\/*)/', "$1\n$2$3", $xml);
	$token   = strtok($xml, "\n");
	$result  = '';
	$pad     = 0;
	$matches = array();

	while($token!==false):
		if (preg_match('/.+<\/\w[^>]*>$/', $token, $matches)) : 
			$indent=0;
		elseif (preg_match('/^<\/\w/', $token, $matches)) :
			$pad--;
		elseif (preg_match('/^<\w[^>]*[^\/]>.*$/', $token, $matches)) :
			$indent=1;
		else :
			$indent = 0; 
		endif;
		$line    = str_pad($token, strlen($token)+$pad, ' ', STR_PAD_LEFT);
		$result .= $line . "\n";
		$token   = strtok("\n");
		$pad    += $indent;
	endwhile; 

	return $result;
}

?>
