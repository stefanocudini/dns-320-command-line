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
       host                       hostname or ip target, default: pulse
       port                       port number for host, default: 80
       -p,--p2p[=on|off]          get or set p2p client state
       -c,--p2p-clear             clear p2p complete list
       -l,--p2p-limit[=down[,up]] get or set p2p speed limit, unlimit: -1
       -D,--download[=url]        list or add url in http downloader
       -C,--download-clear        clear complete http downloads list
       -L,--download-list[=file]  add urls from file list
       -n,--nfs[=on|off]          get or set nfs service
       -f,--ftp[=on|off]          get or set ftp service
       -t,--temp                  get temperature inside
       -T,--time                  get date and time of nas
       -F,--fan[=off|low|high]    get or set fan mode
       -u,--ups                   get ups state
       -d,--disks                 get disks usage
       -s,--shutdown              power off the system
       -r,--restart               restart the system
       -h,--help                  print this help

");


$options = array(
		'p::'=> 'p2p::',
		'c'  => 'p2p-clear',
		'l::'=> 'p2p-limit::',
		'D::'=> 'download::',
		'C'  => 'download-clear',
		'L:' => 'download-list:',
		'n::'=> 'nfs::',
		'f::'=> 'ftp::',
		't'  => 'temp',
		'T'  => 'time',
		'F::'=> 'fan::',
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

$hostport = array_pop($argv);//last parameter
if($argc>1 and $hostport{0}!='-')//if isn't option
{
	if(!checkurl('http://'.$hostport.'/'))
		die("ERROR HOST\n");
	define('HOST', $hostport);
}
else
	define('HOST', 'pulse');


if(count($opts)==0)
	help();

define('USER', 'admin');
define('PASS', 'admin');

define('DIRDOWN','Volume_1/downloads');//target path inside nas for http download
define('DIRBASE',dirname(__FILE__).'/');//path of this script

define('CJAR', DIRBASE.'_cookies.txt');
define('UAGENT', "DNS-320 Command-line Interface");
define('SDELAY', 2);	//delay after post setconfig request, in seconds
define('URLCGI', 'http://'.HOST.'/cgi-bin/');
define('URLXML', 'http://'.HOST.'/xml/');

$urls['login'] = URLCGI.'login_mgr.cgi';
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

$urls['stat'] = URLCGI.'status_mgr.cgi';
$params['statGetStatus'] = array('cmd'=>'cgi_get_status');

$urls['disk'] = URLCGI.'dsk_mgr.cgi';
$params['diskStatus'] = array('cmd'=>'Status_HDInfo');

$urls['sys'] = URLCGI.'system_mgr.cgi';
$params['sysRestart'] = array('cmd'=>'cgi_restart');
$params['sysShutdown'] = array('cmd'=>'cgi_shutdown');
$params['sysGetFan'] = array('cmd'=>'cgi_get_power_mgr_xml');
$params['sysSetFan'] = array(
	'cmd'=>'cgi_fan',
	'f_fan_type'=>0
);
$params['sysGetTime'] = array('cmd'=>'cgi_get_time');

$urls['p2p'] = URLCGI.'p2p.cgi';
$urls['p2pGetSpeed'] = URLXML.'p2p_total_speed.xml';

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
	'f_port_custom'=>'false',
	'f_seed_type'=>0,
	'f_encryption'=>1,
	'f_flow_control_schedule_max_download_rate'=> -1,
	'f_flow_control_schedule_max_upload_rate'=> -1,
	'f_bandwidth_auto'=>'false',
	'f_flow_control_schedule'=>str_repeat('1',168),
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

$urls['down'] = URLCGI.'download_mgr.cgi';
$params['downAddUrl'] = array(
	'f_downloadtype'=>0,
	'f_login_method'=>1,
	'f_type'=>0,
	'f_URL'=>'http://host/path/file',
	'f_dir'=>DIRDOWN,
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

$urls['nfs'] = URLCGI.'account_mgr.cgi';
$params['nfsGetConfig'] = array(
	'cmd'=>'cgi_get_nfs_info'
);
$params['nfsSetConfig'] = array(
	'cmd'=>'cgi_nfs_enable',
	'nfs_status'=>1
);
$params['nfsGetList'] = array(
	'cmd'=>'cgi_get_session',
	'page'=>1,
	'rp'=>10,
	'sortname'=>'undefined',
	'sortorder'=>'undefined',
	'query'=>'',
	'qtype'=>'',
	'f_field'=>'false'
);
$params['nfsGetInfo'] = array(
	'cmd'=>'cgi_get_share_info',
	'name'=>''//share name
);
$urls['ftp'] = URLCGI.'app_mgr.cgi';
$params['ftpGetConfig'] = array(
	'cmd'=>'FTP_Server_Get_Config'
);
$params['ftpSetConfig'] = array(
	'cmd'=>'FTP_Server_Enable',
	'f_state'=>1
);

/*$urls['iso'] = URLCGI.'isomount_mgr.cgi';
$params['isoGetList'] = array(
	'cmd'=>'cgi_get_iso_share',
	'page'=>1,
	'rp'=>10,
	'sortname'=>'undefined',
	'sortorder'=>'undefined',
	'query'=>'',
	'qtype'=>'',
	'f_field'=>'false'
);
$params['isoAddShare'] = array(
	'cmd'=>'cgi_set_iso_share',
	'path'=>'/mnt/HD/HD_b2/progs/Adobe Dreamweaver CS5.iso',
	'name'=>'Adobe Dreamweaver CS5',
	'comment'=>'',
	'read_list'=>'#nobody#,#@allaccount#',
	'invalid_users'=>'',
	'ftp'=>'true',
	'ftp_anonymous'=>'n'
);
$params['isoDelShare'] = array(
	'cmd'=>'cgi_del_iso_share',
	'sharename'=>'Adobe Dreamweaver CS5',
	'path'=>'/mnt/isoMount/Adobe Dreamweaver CS5',
	'host'=>'*'
);//*/


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
			if(p2pCheckOn())
				echo "P2P: On\n";
			else
				die("P2P: Off\n");
			
			$p2pSpeed = p2pGetSpeed();
			echo " Speed:  ".$p2pSpeed['down']." KBps / ".$p2pSpeed['up']." KBps\n";
			$p2pConf = p2pGetConfig();
			echo " Limits: ".$p2pConf['bandwidth_downlaod_rate']." KBps / ".$p2pConf['bandwidth_upload_rate']." KBps\n";
			p2pPrintList();
		break;
		
		case 'c':
		case 'p2p-clear':
			if(p2pCheckOn())
				echo "P2P: On\n";
			else
				die("P2P: Off\n");
			
			p2pClearList();
			p2pPrintList();
		break;
		
		case 'l':
		case 'p2p-limit':
			if(p2pCheckOn())
				echo "P2P: On\n";
			else
				die("P2P: Off\n");
			
			$optval = strstr(',',$optval) ? $optval : $optval.',';
			list($down,$up) = @explode(',',$optval);
			
			if(!empty($down) or !empty($up))
				p2pSetConfig( array('down'=>intval($down?$down:-1), 'up'=>intval($up?$up:-1)) );

			$p2pConf = p2pGetConfig();
			echo " Limits: ".$p2pConf['bandwidth_downlaod_rate']." KBps / ".$p2pConf['bandwidth_upload_rate']." KBps\n";
		break;

		case 'D':
		case 'download':
			if(!empty($optval))
				downAddUrl($optval) or die("ERROR URL: ".$optval);
			downPrintList();
		break;

		case 'C':
		case 'download-clear':
			$dd = downGetList();
			foreach($dd as $d)
				if($d['status']=='complete' or $d['status']=='failed')
					downDelUrl($d['id']);
			downPrintList();
		break;

		case 'L':
		case 'download-list':
			file_exists($optval) or die("ERROR FILE: ".$optval);
			
			foreach(file($optval,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES) as $url)
				downAddUrl($url);
			downPrintList();
		break;		

		case 'n':
		case 'nfs':
			switch($optval)
			{
				case 'on':
					nfsSetConfig( array('on'=>true) );
				break;
				case 'off':
					nfsSetConfig( array('on'=>false) );
				break;
			}
			$nfsConf = nfsGetConfig();
			echo "NFS: ".($nfsConf['enable']?'On':'Off')."\n";							     
			nfsPrintList();
		break;
		
		case 'f':
		case 'ftp':
			switch($optval)
			{
				case 'on':
					ftpSetConfig( array('on'=>true) );
				break;
				case 'off':
					ftpSetConfig( array('on'=>false) );
				break;
			}
			$ftpConf = ftpGetConfig();
			echo "FTP: ".($ftpConf['state']?'On':'Off');
		break;
				
		case 'u':
		case 'ups':
			$u = upsGetInfo();
			echo "UPS:\t".($u ? $u['stat']."\n".
			     " Battery: ".$u['bat'] : 'Off');
		break;

		case 't':
		case 'temp':
			echo "TEMPERATURE:\t".sysGetTemp().'°C';
		break;

		case 'T':
		case 'time':
			echo "TIME:\t".sysGetTime();
		break;
		
		case 'F':
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
 					 "  Free: ".bytesConvert($disk['free_size']*1000)."\n".
					 "  Used: ".$disk['used_rate']."\n";
			}
		break;

		case 's':
		case 'shutdown':
			if(!confirm("Are you sure you want to poweroff NAS now?")) break;
			echo "Shutdown system...\n";
			sysShutdown();
		break;
		
		case 'r':
		case 'restart':
			if(!confirm("Are you sure you want to restart NAS now?")) break;
			echo "Restart system...\n";
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
	global $debug;
	if(DEBUG or $debug)
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
	$ff = array('Off','Low','High');
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

function p2pCheckOn()
{
	$p2pConf = p2pGetConfig();
	return  (bool)$p2pConf['p2p'];
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

	debug("p2pSetConfig:\n".print_r($params['p2pSetConfig'],true));
	
	http_post_request($urls['p2p'],$params['p2pSetConfig']);
	sleep(SDELAY);
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

function p2pPrintList()
{
	$pp = p2pGetList();	
	echo " Torrents: ".count($pp)."\n";
	foreach($pp as $p)
		echo '  '.ucwords($p['status'])."\t".$p['progress']."\t".$p['speed']."\t".basename($p['file'])."\n";
}

function p2pClearList()
{
	global $urls;
	global $params;
	http_post_request($urls['p2p'],$params['p2pClearList']);
}

function p2pGetSpeed()
{
	global $urls;
	$s = xml2array( http_post_request($urls['p2pGetSpeed'],array()) );
	return array('down'=>$s['download_rate'], 'up'=>$s['upload_rate']);
}

function downAddUrl($url)
{
	global $urls;
	global $params;
	
	//add check url controll syntax 
	
	$params['downAddUrl']['f_URL']= $url;
	$params['downAddUrl']['f_date']= date("m/d/Y");
	$params['downAddUrl']['f_hour']= date("h");
	$params['downAddUrl']['f_min']= date("i");
	//control time zone from dns-320 and where execute script
	http_post_request($urls['down'],$params['downAddUrl']);
	sleep(SDELAY);
	return true;
}

function downDelUrl($idurl)
{
	global $urls;
	global $params;
	$params['downDelUrl']['f_idx']= $idurl;
	http_post_request($urls['down'],$params['downDelUrl']);
	#sleep(SDELAY);
}

function downGetList()
{
	global $urls;
	global $params;
	$L = array();
	$dd = xml2array( http_post_request($urls['down'],$params['downGetList']) );

	if(!isset($dd['row']))
		return $L;

	$rows = isset($dd['row'][0]) ? $dd['row'] : array($dd['row']);
	//patch for single/multiple downGetList response
	
	foreach($rows as $U)
	{
		$u = $U['cell'];
		if(strstr($u[3],'status_download')) $s = 'download';
		elseif(strstr($u[3],'icon_stop'))   $s = 'stopped';
		elseif(strstr($u[3],'status_fail')) $s = 'failed';
		elseif(strstr($u[3],'status_ok'))   $s = 'complete';
	
		preg_match("/.*>(.*)<.*/", $u[2], $p);
		$L[]= array('progress'=> $p[1].'%',
					'status'=>   $s,
					'speed'=>    $u[4].'ps',
					'url'=>      $u[0],
					'id'=>       $u[8]);
	}
	return $L;
}

function downPrintList()
{
	$dd = downGetList();
	echo "DOWNLOADS: ".count($dd)."\n";
	foreach($dd as $d)
		echo ' '.ucwords($d['status'])."\t".$d['progress']."\t".$d['speed']."\t\t".basename($d['url'])."\n";
}

function nfsGetConfig()
{
	global $urls;
	global $params;
	return xml2array( http_post_request($urls['nfs'],$params['nfsGetConfig']) );
}

function nfsSetConfig($sets=array())
{
	global $urls;
	global $params;
	if(isset($sets['on'])) $params['nfsSetConfig']['nfs_status']= $sets['on'] ? 1:0;

	debug("nfsSetConfig:\n".print_r($params['nfsSetConfig'],true));
	
	http_post_request($urls['nfs'],$params['nfsSetConfig']);
	sleep(SDELAY);
}

function nfsGetInfo($name)//info about a nfs share
{
	global $urls;
	global $params;
	$params['nfsGetInfo']['name'] = $name;
	return xml2array( http_post_request($urls['nfs'],$params['nfsGetInfo']) );
}

function nfsGetList()
{
	global $urls;
	global $params;
	$shares = xml2array( http_post_request($urls['nfs'],$params['nfsGetList']) );
	
	$nfss = array();
	foreach($shares['row'] as $s)
	{
		$name = $s['cell'][0];
		$i = nfsGetInfo($name);
		if((bool)$i['nfs']['status'])
			$nfss[$name] = array('path'=>$i['path'],
								 'realpath'=>$i['nfs']['real_path'],
								 'host'=>$i['nfs']['host'],
 								 'write'=>($i['nfs']['write']=='Yes'?1:0),
							     'recycle'=> (bool)$i['recycle']?1:0);
	}
	return $nfss;
}

function nfsPrintList()
{
	$nfss = nfsGetList();
	$mlen = max(array_map('strlen',array_keys($nfss)));
	foreach($nfss as $name=>$n)
	echo ' '.str_pad($name.':',$mlen+2,' ').$n['path']."\t\t".
							$n['host'].
							($n['write']?',rw':',ro').
							($n['recycle']?',recycle':'')."\n";
}

function ftpGetConfig()
{
	global $urls;
	global $params;
	return xml2array( http_post_request($urls['ftp'],$params['ftpGetConfig']) );
}

function ftpSetConfig($sets=array())
{
	global $urls;
	global $params;
	
	if(isset($sets['on'])) $params['ftpSetConfig']['f_state']= $sets['on'] ? 1:0;

	debug("ftpSetConfig:\n".print_r($params['ftpSetConfig'],true));
	
	http_post_request($urls['ftp'],$params['ftpSetConfig']);
	sleep(SDELAY);
}
				
////////////////////////////////////////


function login()		//LOGIN
{
	global $urls;
	global $params;

	list($head,$body) = http_post_request($urls['login'], $params['loginSet'], true);
#	debug(print_r($head,true));
//login conditions:
//RESP OK: "Set-Cookie:username=admin; path=/"
//RESP ERROR: "Location:http://host/web/relogin.html"
	return (isset($head['Set-Cookie']) and strstr($head['Set-Cookie'],'username='.USER) );
}

function http_post_request($url, $pdata=null, $getHeaders=false)
{
	$pdata = is_array($pdata) ? http_build_query($pdata) : $pdata;
	
	$encPass = textToBase64(encRC4(USER,PASS));//view /web/pages/function/rc4.js
	$cookie = 'username='.USER.'; rembMe=checked; uname='.USER.'; password='.$encPass;#.'; path=/';
			
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url );
	curl_setopt($ch, CURLOPT_POSTFIELDS, $pdata);
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_HEADER, 1);	
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_COOKIE, $cookie);
	curl_setopt($ch, CURLOPT_COOKIEJAR, CJAR);
	curl_setopt($ch, CURLOPT_COOKIEFILE, CJAR);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_USERAGENT, UAGENT);
#	curl_setopt($ch, CURLOPT_HTTPHEADER, array("Connection: close"));
	curl_setopt($ch, CURLINFO_HEADER_OUT, true);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
	$resp = curl_exec($ch);
	$info = curl_getinfo($ch);
	curl_close($ch);
	
	$body = ''; $head = array();
	$resRows = explode("\r\n",substr($resp,0, $info['header_size']));
	foreach($resRows as $row)
		$head[ current(explode(': ',$row)) ] = next(explode(': ',$row));
	//split http head in headers key=>value
	
	$reqRows = explode("\r\n", trim($info['request_header']));

	if($info['download_content_length']>0)
		$body = substr($resp, -$info['download_content_length']);
	else
		$body = next(explode("\r\n\r\n",$resp));
	
	$resp = $getHeaders ? array($head, $body) : $body;
	
	debug(#"URL:    ".$url."\n".
		  "REQUEST: ".implode("\n".
		  "         ",$reqRows)."\n".
		  "POST:    ".$pdata."\n\n".
		  "RESPONSE: ".implode("\n".
		  "          ",$resRows));

	return $resp;
}

function confirm($text)
{
	echo "$text [y/n] ";
	return trim(fgets(STDIN))=='y';
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

function encRC4($key, $text)
{
	$kl=strlen($key);
	$s=array();

	for($i=0; $i<256; $i++)
		$s[$i]=$i;

	$y=0;
	$x=$kl;
	while($x--) {
		$y=(charCodeAt($key,$x) + $s[$x] + $y) % 256;
		$t=$s[$x]; $s[$x]=$s[$y]; $s[$y]=$t;
	}
	$x=0;  $y=0;
	$z="";
	for($x=0; $x<strlen($text); $x++) {
		$x2=$x & 255;
		$y=( $s[$x2] + $y) & 255;
		$t=$s[$x2]; $s[$x2]=$s[$y]; $s[$y]=$t;
		$z .= chr( charCodeAt($text,$x) ^ $s[($s[$x2] + $s[$y]) % 256] );
	}
	return $z;
}//*/


function charCodeAt($str, $i)
{
  return ord(substr($str, $i, 1));
}

function textToBase64($t)
{
	$tab = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/";
	$r=''; $m=0; $a=0; $tl=strlen($t)-1;
	
	for($n=0; $n<=$tl; $n++)
	{
		$c = charCodeAt($t,$n);

		$r .= substr($tab, (($c << $m | $a) & 63) ,1);
		$a = $c >> (6-$m);
		$m+=2;
		if($m==6 || $n==$tl) {
			$r .= substr($tab, $a ,1);
			$m=0;
			$a=0;
		}
	}
	return $r;
}

?>
