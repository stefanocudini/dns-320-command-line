#!/usr/bin/php
<?
/*
COMMAND-LINE INTERFACE for D-LINK Sharecenter DNS-320 Pulse
https://bitbucket.org/zakis_/dns-320-command-line

Copyleft Stefano Cudini 2012
stefano.cudini@gmail.com

requirements: php5-cli,	php5-curl
************************************************/

define('DEBUG', false);
		
$options = array(
		'p::'=> 'p2p::',
		'c'  => 'p2p-clear',
		'l::'=> 'p2p-limit::',
		'a::'=> 'p2p-auto::',		
		's::'=> 'p2p-start::',
		'o::'=> 'p2p-stop::',
		'x::'=> 'p2p-delete::',
		'D::'=> 'download::',
		'C'  => 'download-clear',
		'L:' => 'download-list:',
		'N::'=> 'nfs::',
		'F::'=> 'ftp::',
		't'  => 'temp',
		'T'  => 'time',
		'A::'=> 'fan::',
		'u'  => 'ups',
		'U'  => 'usb',
		'M'  => 'usb-umount',		
		'd'  => 'disks',
		'S'  => 'shutdown',
		'P'  => 'shutdown-prog',
		'R'  => 'restart',
		'O'  => 'logout',		
		'f'  => 'force',
		'q'  => 'quiet',		
		'h'  => 'help');

define('HELP',"

Usage: pulse.php options [host[:port]]

       host                        hostname or ip target, default: pulse
       port                        port number for host, default: 80
OPTIONS:
       -p,--p2p[=on|off]           get or set p2p client state
       -c,--p2p-clear              clear p2p complete list
       -l,--p2p-limit[=down[,up]]  get or set p2p speed limit, unlimit: -1
       -a,--p2p-auto[=on|off]      get or set p2p automatic download       
       -s,--p2p-start[=id,id,...]  start all or specific torrent download
       -o,--p2p-stop[=id,id,...]   stop all or specific torrent download
       -x,--p2p-delete[=id,id,...] delete specific torrent download       
       -D,--download[=url]         list or add url in http downloader
       -C,--download-clear         clear complete http downloads list
       -L,--download-list[=file]   add urls from file list
       -N,--nfs[=on|off]           get or set nfs service
       -F,--ftp[=on|off]           get or set ftp service
       -t,--temp                   get temperature inside
       -T,--time                   get date and time of nas
       -A,--fan[=off|low|high]     get or set fan mode
       -u,--ups                    get ups state
       -U,--usb                    get usb disk/flash info
       -M,--usb-umount             umount usb disk/flash
       -d,--disks                  get disks usage
       -S,--shutdown               power off system now
       -P,--shutdown-prog          get list schedule power off
       -R,--restart                restart system
       -O,--logout                 logout user admin from current host
       -f,--force                  force execute of comfirm command
       -q,--quiet                  quiet mode, suppress output
       -h,--help                   print this help

");

if(version_compare(PHP_VERSION, '5.3.0', '<'))
	$opts = getopt(implode('',array_keys($options)));
else
	$opts = getopt(implode('',array_keys($options)),array_values($options));

debug(print_r(array('OPTIONS'=>$opts),true));

$force = false;//force confirmation commands
$quiet = false;//no verbose mode

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

define('DIRDOWN', 'Volume_1/downloads');//target path inside nas for http download
define('DIRBASE', dirname(__FILE__).'/');//path of this script

define('USECURL', in_array('curl',get_loaded_extensions()) );//check php5-curl is present
define('CJAR', DIRBASE.'_cookies.txt');
define('UAGENT', "DNS-320 Command-line Interface (".(USECURL?'Curl':'Socket').')');
define('TIMEOUT', 10);	//connection timeout
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
$params['loginLogout'] = array(
	'cmd'=>'logout',
	'name'=>USER
);

$urls['stat'] = URLCGI.'status_mgr.cgi';
$params['statGetStatus'] = array('cmd'=>'cgi_get_status');
//about: ups, temperature, usb
$urls['usbUmount'] = URLCGI.'system_mgr.cgi?cmd=cgi_umount_flash&_='.uniqid();
//is a GET request

$urls['disk'] = URLCGI.'dsk_mgr.cgi';
$params['diskStatus'] = array('cmd'=>'Status_HDInfo');

$urls['sys'] = URLCGI.'system_mgr.cgi';
$params['sysRestart'] = array('cmd'=>'cgi_restart');
$params['sysShutdown'] = array('cmd'=>'cgi_shutdown');
$params['sysShutGetProg'] = array('cmd'=>'cgi_get_power_mgr_xml');
$params['sysShutSetProg'] = array(
	'cmd'=>'cgi_power_off_sch',
	'f_power_off_enable'=>0,
	'schedule'=>'0 0 0'//es. '1 3 23,6 22 0' -> lun 3:23, sab 22:00
);
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
	'rp'=>40,
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
	'f_port_custom'=>'true',
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
	'rp'=>40,
	'sortname'=>'undefined',
	'sortorder'=>'undefined',
	'query'=>'',
	'qtype'=>'',
	'f_field'=>0
);
$params['p2pClearList'] = array('cmd'=>'p2p_del_all_completed');
$params['p2pStopFile'] = array(
	'cmd'=>'p2p_pause_torrent',
	'f_torrent_index'=>0
);
$params['p2pStartFile'] = array(
	'cmd'=>'p2p_start_torrent',
	'f_torrent_index'=>0
);
$params['p2pDelFile'] = array(
	'cmd'=>'p2p_del_torrent',
	'f_torrent_index'=>0
);

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
	'rp'=>40
);
$params['downDelUrl'] = array(
	'cmd'=>'Downloads_Schedule_Del',
	'f_idx'=>'',
	'f_field'=>USER
);
$params['downGetList'] = array(
	'cmd'=>'Downloads_Schedule_List',
	'page'=>1,
	'rp'=>40,
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
	'rp'=>40,
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

/*
//TODO
$urls['iso'] = URLCGI.'isomount_mgr.cgi';
$params['isoGetList'] = array(
	'cmd'=>'cgi_get_iso_share',
	'page'=>1,
	'rp'=>40,
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


//START RUN!

ob_start();

login() or die("ERROR LOGIN\n");

foreach($opts as $opt=>$optval)
{
	switch($opt)
	{
		case 'p':
		case 'p2p':
			$p2pConf = p2pInitConfig();
			switch($optval)
			{
				case 'on':
					p2pSetConfig( array('on'=>true) );
				break;
				case 'off':
					p2pSetConfig( array('on'=>false) );
				break;
			}
			if(!p2pCheckOn())//new p2pGetConfig()
			{
				echo "P2P: Off\n";
				break;
			}
			echo "P2P: On\n";
		
			$p2pSpeed = p2pGetSpeed();
			echo " Speed:  ".$p2pSpeed['down']." KBps / ".$p2pSpeed['up']." KBps\n";
			$p2pConf = p2pGetConfig();
			echo " Limits: ".$p2pConf['bandwidth_downlaod_rate']." KBps / ".$p2pConf['bandwidth_upload_rate']." KBps\n";
			echo " AutoDownload: ".($p2pConf['autodownload']?'On':'Off')."\n";			
			echo p2pPrintList();
		break;
		
		case 'c':
		case 'p2p-clear':
			if(!p2pCheckOn())
			{
				echo "P2P: Off\n";
				break;
			}
			echo "P2P: On\n";
			
			p2pClearList();
			echo p2pPrintList();
		break;
		
		case 'l':
		case 'p2p-limit':
			$p2pConf = p2pInitConfig();
			if(!p2pCheckOn())
			{
				echo "P2P: Off\n";
				break;
			}
			echo "P2P: On\n";
			
			list($down,$up) = @explode(',', strstr(',',$optval) ? $optval : $optval.',' );
			
			if(!empty($down) or !empty($up))
				p2pSetConfig( array('down'=>intval($down?$down:-1), 'up'=>intval($up?$up:-1)) );

			$p2pConf = p2pGetConfig();
			echo " Limits: ".$p2pConf['bandwidth_downlaod_rate']." KBps / ".$p2pConf['bandwidth_upload_rate']." KBps\n";
		break;
		
		case 'a':
		case 'p2p-auto':
			if(!p2pCheckOn())
			{
				echo "P2P: Off\n";
				break;
			}
			$p2pConf = p2pInitConfig();
			switch($optval)
			{
				case 'on':
					p2pSetConfig( array('auto'=>true) );
				break;
				case 'off':
					p2pSetConfig( array('auto'=>false) );
				break;
			}
			$p2pConf = p2pGetConfig();
			echo " AutoDownload: ".($p2pConf['autodownload']?'On':'Off')."\n";
		break;	
		
		case 's':
		case 'p2p-start':
			if(!p2pCheckOn())
			{
				echo "P2P: Off\n";
				break;
			}
			$optvals = @explode(',', strstr(',',$optval) ? $optval : $optval.',' );
			$pp = p2pGetList();
			foreach($pp as $p)
				if($optval=='' or in_array($p['id'],$optvals))
					p2pStartFile($p['id']);			
			echo p2pPrintList();
		break;		

		case 'o':
		case 'p2p-stop':
			if(!p2pCheckOn())
			{
				echo "P2P: Off\n";
				break;
			}
			$optvals = @explode(',', strstr(',',$optval) ? $optval : $optval.',' );
			$pp = p2pGetList();
			foreach($pp as $p)
				if($optval=='' or in_array($p['id'],$optvals))
					p2pStopFile($p['id']);
			echo p2pPrintList();
		break;	
		
		case 'x':
		case 'p2p-delete':
			if(!p2pCheckOn())
			{
				echo "P2P: Off\n";
				break;
			}
			$optvals = @explode(',', strstr(',',$optval) ? $optval : $optval.',' );
			$pp = p2pGetList();
			foreach($pp as $p)
				if(in_array($p['id'],$optvals))//remove only specific torrent id, never all in one time
					p2pDelFile($p['id']);
			echo p2pPrintList();
		break;	
		
		case 'D':
		case 'download':
			if(!empty($optval))
				if(!downAddUrl($optval))
				{
					echo "ERROR URL: ".$optval;
					break;
				}
			echo downPrintList();
		break;

		case 'C':
		case 'download-clear':
			$dd = downGetList();
			foreach($dd as $d)
				if($d['status']=='complete' or $d['status']=='failed')
					downDelUrl($d['id']);
			echo downPrintList();
		break;

		case 'L':
		case 'download-list':
			if(!file_exists($optval))
			{
				echo "ERROR FILE: ".$optval;
				break;
			}
			foreach(file($optval,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES) as $url)
				downAddUrl($url);
			echo downPrintList();
		break;		

		case 'N':
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
			echo nfsPrintList();
		break;
		
		case 'F':
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

		case 'U':
		case 'usb':
			$u = usbGetInfo();
			echo "USB-DISK:\t".($u ? "Mounted\n".
			     ' '.$u['name'].': '.$u['disk'] : 'Off');
		break;

		case 'M':
		case 'usb-umount':
			usbUmount();
			$u = usbGetInfo();
			echo "USB-DISK:\t".($u ? "Mounted\n" : 'Off');
		break;
		
		case 't':
		case 'temp':
			echo "TEMP:\t".sysGetTemp().'°C';
		break;

		case 'T':
		case 'time':
			echo "TIME:\t".sysGetTime();
		break;
		
		case 'A':
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

		case 'S':
		case 'shutdown':
			if(!confirm("Are you sure you want to poweroff NAS now?")) break;
			echo "Shutdown system...\n";
			sysShutdown();
		break;
		case 'P':
		case 'shutdown-prog':
			if(!sysShutProgCheckOn())
			{
				echo "Shutdown Schedule: Off\n";
				break;
			}
			echo "Shutdown Schedule: On\n";

			$shut = sysShutGetProg();
			
			foreach($shut as $d=>$h)
				echo " $d: $h\n";

			//code for futures sysShutSetProg()
			//list($min,$sec) = @explode(':', strstr(':',$optval) ? $optval : ':'.$optval );
			//if(!empty($min) or !empty($sec))
			//	p2pSetConfig( array('down'=>intval($down?$down:-1), 'up'=>intval($up?$up:-1)) );
		break;
		
		case 'R':
		case 'restart':
			if(!confirm("Are you sure you want to restart NAS now?")) break;
			echo "Restart system...\n";
			sysRestart();
		break;

		case 'O':
		case 'logout':
			echo "Destroy login session from current host...\n";
			logout();
		break;
		
		case 'f':
		case 'force':
			$force = true;
		break;

		case 'q':
		case 'quiet':
			$quiet = true;
		break;		
		
		case 'h':
		case 'help':
		default:
			help();
	}
	echo "\n";
}

$OUT = ob_get_clean();

if(!$quiet)
	echo $OUT;

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

function human2bytes($t)
{
	if( preg_match("#([0-9.]{1,})(B|KB|MB|GB|TB)#",$t,$maches) )
	{
		$num = $maches[1];
		$unit = $maches[2];
		$mm = array('B'=>1,
					'KB'=>1024,
					'MB'=>1024*1024,
					'GB'=>1024*1024*1024,
					'TB'=>1024*1024*1024*1024);
		return round( floatval($num) * $mm[$unit] );
	}
	else
		return false;
}

function bytes2human($size)
{
    $mod = 1024;
    $units = array('B','KB','MB','GB','TB');
    for ($i = 0; $size > $mod; $i++) {
        $size /= $mod;
    }
    return round($size, 1).$units[$i];
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

function sysShutProgCheckOn()
{
	global $urls;
	global $params;
	$shut = xml2array( http_post_request($urls['sys'],$params['sysShutGetProg']) );
	return (bool)$shut['power_off_enable'];
}

function sysShutGetProg()
{
	global $urls;
	global $params;
	$shut = xml2array( http_post_request($urls['sys'],$params['sysShutGetProg']) );
	$days = array('Dom','Lun','Mar','Mer','Gio','Ven','Sab');
	$ret = array();
	if((int)$shut['power_off_enable'])
		for($i=1; $i<=(int)$shut['power_off_sch_count']; $i++)
		{
			list($x,$h,$m,$d) = explode(':',$shut["power_off_sch_$i"]);
			$ret[ $days[$d] ]= "$h:$m";
		}

	return $ret;
/*<?xml version="1.0" encoding="UTF-8" ?>
<power>
<hdd_hibernation_enable>0</hdd_hibernation_enable>
<turn_off_time>300</turn_off_time>
<recovery_enable>1</recovery_enable>
<power_off_sch_count>2</power_off_sch_count>
<power_off_sch_1>2:12:5:1:*</power_off_sch_1>
<power_off_sch_2>2:22:6:6:*</power_off_sch_2>
<power_off_enable>1</power_off_enable>
<fan>0</fan>
</power>*/
}

function upsGetInfo()
{
	global $urls;
	global $params;
	$ups = xml2array( http_post_request($urls['stat'],$params['statGetStatus']) );
	if($ups['usb_type']=='UPS')
	{
		return array(
			'bat'  => $ups['battery'],
			'stat' => $ups['ups_status']);
	}
	else
		return false;
}

function usbGetInfo()
{
	global $urls;
	global $params;
	$usb = xml2array( http_post_request($urls['stat'],$params['statGetStatus']) );
	
	if($usb['usb_type']=='FLASH')
	{
		return array(
			'name' => $usb['flash_info']['Manufacturer'].' '.$usb['flash_info']['Product'],
			'disk' => $usb['flash_info']['Partition']);
	}
	else
		return false;
}

function usbUmount()
{
	global $urls;
	global $params;
	if(!is_array(usbGetInfo())) return false;
	http_post_request($urls['usbUmount']);//GET request
	sleep(SDELAY);
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
	return sprintf("%02d/%02d/%04d", $t['day'], $t['mon'], $t['year']).', '.
		   sprintf("%02d:%02d:%02d", $t['hour'], $t['min'], $t['sec']);
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

function p2pInitConfig()
{
	global $urls;
	global $params;	
/*
    [result] => 1
    [p2p] => 1
    [port] => true
    [port_number] => 6881
    [bandwidth] => true
    [bandwidth_upload_rate] => -1
    [bandwidth_downlaod_rate] => -1
    [seeding] => 0
    [seeding_percent] => 0
    [seeding_mins] => 0
    [encryption] => 1
    [autodownload] => 0
    [current_ses_state] => 0
    [flow_control_download_rate] => -1
    [flow_control_upload_rate] => -1
    [flow_control] => 111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111
*/
	$c = p2pGetConfig();
	return $params['p2pSetConfig'] = array(
		'f_P2P'=> $c['p2p'],
		'f_auto_download'=> $c['autodownload'],
		'f_port_custom'=> $c['p2p']?'true':'false',
		'f_seed_type'=> $c['seeding'],
		'f_encryption'=> $c['encryption'],
		'f_flow_control_schedule_max_download_rate'=> $c['bandwidth_downlaod_rate'],
		'f_flow_control_schedule_max_upload_rate'=> $c['bandwidth_upload_rate'],
		'f_bandwidth_auto'=> 'false',
		'f_flow_control_schedule'=>$c['flow_control'],#str_repeat('1',168),
		'cmd'=>'p2p_set_config',
		'tmp_p2p_state'=>''
	);
}

function p2pGetConfig()
{
	global $urls;
	global $params;
	$c = xml2array( http_post_request($urls['p2p'],$params['p2pGetConfig']) );
	return $c;
}

function p2pSetConfig($sets=array())
{
	global $urls;
	global $params;
	if(isset($sets['on']))   $params['p2pSetConfig']['f_P2P']= $sets['on'] ? 1:0;
	if(isset($sets['down'])) $params['p2pSetConfig']['f_flow_control_schedule_max_download_rate']= $sets['down'];
	if(isset($sets['up']))   $params['p2pSetConfig']['f_flow_control_schedule_max_upload_rate']= $sets['up'];
	if(isset($sets['auto'])) $params['p2pSetConfig']['f_auto_download']= $sets['auto'] ? 1:0;
	
	debug(print_r(array('p2pSetConfig'=>$params['p2pSetConfig']),true));
	
	http_post_request($urls['p2p'],$params['p2pSetConfig']);
	sleep(SDELAY);
}

function p2pStartFile($idtorrent)
{
	global $urls;
	global $params;
	$params['p2pStartFile']['f_torrent_index']= $idtorrent;	
	http_post_request($urls['p2p'],$params['p2pStartFile']);
}

function p2pStopFile($idtorrent)
{
	global $urls;
	global $params;
	$params['p2pStopFile']['f_torrent_index']= $idtorrent;	
	http_post_request($urls['p2p'],$params['p2pStopFile']);
}

function p2pDelFile($idtorrent)
{
	global $urls;
	global $params;
	$params['p2pDelFile']['f_torrent_index']= $idtorrent;	
	http_post_request($urls['p2p'],$params['p2pDelFile']);
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
		elseif(strstr($p[4],'status_queue'))  $s = 'stopped ';
		elseif(strstr($p[4],'status_upload')) $s = 'complete';
		
		preg_match("/.*>(.*)<.*/", $p[0], $f);//file
		preg_match("/.*>(.*)<.*/", $p[3], $g);//progress
		$perc = intval(trim($g[1]));
		$size_tot = $p[2];
		$tot = human2bytes($size_tot);
		$size_com = bytes2human(($tot/100)*$perc);

//TODO time remain
		$P[]= array('progress'=> $perc,
					'status'=>   $s,
					'speed'=>    $p[5],
					'file'=>     substrStrip($f[1], 40),
					'size-tot'=> $size_tot,
					'size-com'=> $size_com,
					'id'=>       $p[7]);
	}
	
	if(count($P)==0) return array();

	foreach($P as $k=>$r)
		$progress[$k]= $r['progress'];
		
	array_multisort($progress, SORT_DESC, $P);

	return $P;
}

function p2pPrintList()
{
	$pp = p2pGetList();	
	$out = " Torrents: ".count($pp)."\n";
	foreach($pp as $p)
		$out .= '  #'.$p['id']."\t".ucwords($p['status'])."\t".$p['progress']."%\t(".$p['size-com']." of ".$p['size-tot'].")\t".$p['speed']."\t".basename($p['file'])."\n";
	return $out;
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
	
	if(!strstr($url,"http://")) return false;//add check url controll syntax 
	
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
	$out .= "DOWNLOADS: ".count($dd)."\n";
	foreach($dd as $d)
		$out .= ' '.ucwords($d['status'])."\t".$d['progress']."\t".$d['speed']."\t\t".basename($d['url'])."\n";
	return $out;
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
	#$mlen = max(array_map('strlen',array_keys($nfss)));
	$out ='';
	foreach($nfss as $name=>$n)
		$out .= ' '.#str_pad($name.':',$mlen+2,' ')
				$n['path']."\t\t".
				$n['host'].
				($n['write']?',rw':',ro').
				($n['recycle']?',recycle':'')."\n";
	return $out;
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

	debug(print_r(array('ftpSetConfig'=>$params['ftpSetConfig']),true));
		
	http_post_request($urls['ftp'],$params['ftpSetConfig']);
	sleep(SDELAY);
}
				
////////////////////////////////////////


function login()		//LOGIN
{
	global $urls;
	global $params;

	list($head,$body) = http_post_request($urls['login'], $params['loginSet'], true);
	debug(print_r(array('LOGIN'=>$head),true));
//login conditions:
//RESP OK: "Set-Cookie:username=admin; path=/"
//RESP ERROR: "Location:http://host/web/relogin.html"
	return (isset($head['Set-Cookie']) and strstr($head['Set-Cookie'],'username='.USER) );
}

function logout()		//LOGOUT
{
	global $urls;
	global $params;

	list($head,$body) = http_post_request($urls['login'], $params['loginLogout'], true);
	debug(print_r(array('LOGOUT'=>$head),true));
	return (isset($head['Set-Cookie']) and strstr($head['Set-Cookie'],'username='.USER) );
}

function confirm($text)
{
	global $force;
	if($force) return true;
	file_put_contents('php://stderr',"$text [y/n] ");
	return trim(fgets(STDIN))=='y';
}

function xml2array($xml)
{	
	return (empty($xml) ? array() : json_decode(json_encode(simplexml_load_string($xml)),true) );
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


///////////

function http_post_request($url, $pdata=null, $getHeaders=false)
{
	if(USECURL)
		return http_post_requestCurl($url, $pdata, $getHeaders);
	else
		return http_post_requestSock($url, $pdata, $getHeaders);
}

function http_post_requestSock($url, $pdata=null, $getHeaders=false)//without Follow Location implementation
{
#	$url .= '?dd';
	$isget = parse_url($url,PHP_URL_QUERY) ? true : false;//if contain get parameters, then is get request

	$urlinfo = parse_url($url);
	$port = isset($urlinfo["port"]) ? $urlinfo["port"] : 80;

	$encPass = textToBase64(encRC4(USER,PASS));//view /web/pages/function/rc4.js
	$cookie = 'username='.USER.'; rembMe=checked; uname='.USER.'; password='.$encPass;#.'; path=/';

	$baseReq =  "Host: ".$urlinfo["host"]."\r\n".
				"User-Agent: ".UAGENT."\r\n".
				"Accept: */*"."\r\n".
				"Cookie: ".$cookie."\r\n";
	$protoReq = " HTTP/1.0\r\n";	//dont use HTTP/1.1 because server return Transfer-Encoding: chunked and body will be splitted!
	
	if(!$isget)
	{
		$pdata = is_array($pdata) ? http_build_query($pdata) : $pdata;	
		$reqH = "POST ".$urlinfo["path"] . $protoReq.
				"Content-length: ".strlen($pdata)."\r\n".
				"Content-type: application/x-www-form-urlencoded\r\n";
	}
	else//switch to GET request
	{
		$pdata = '';
		$reqH = "GET ".$urlinfo["path"].( isset($urlinfo["query"]) ? '?'.$urlinfo["query"] : '') . $protoReq;
	}

	$req = $reqH.
		   $baseReq.
		   "\r\n".
		   $pdata;
	
	$fp = fsockopen($urlinfo["host"], $port);
	stream_set_timeout($fp, 0, TIMEOUT * 1000);
	fputs($fp, $req);
	$resp='';
	while(!feof($fp))
		$resp .= fread($fp, 8192);
	fclose($fp);
	//////////////////

	$info['header_size'] = strpos($resp, "\r\n\r\n") + 4;
	$info['request_header'] = $reqH;
	$info['download_content_length'] = strlen($resp) - $info['header_size'];
	
	$body = ''; $head = array();
	$resRows = explode("\r\n",substr($resp,0, $info['header_size']));
	foreach($resRows as $row)
		$head[ current(explode(': ',$row)) ] = next(explode(': ',$row));
	//split http head in headers key=>value
	
	$reqRows = explode("\r\n", trim($info['request_header']));

	if($info['download_content_length']>0)
		$body = substr($resp, -$info['download_content_length']);

	$resp = $getHeaders ? array($head, $body) : $body;
	
	debug(#"URL:    ".$url."\n".
		  "REQUEST: ".implode("\n".
		  "         ",$reqRows)."\n".
		  "POST:    ".$pdata."\n\n".
		  "RESPONSE: ".implode("\n".
		  "          ",$resRows));
	return $resp; 
} 

function http_post_requestCurl($url, $pdata=null, $getHeaders=false)
{
//	$url .= '?dd';
	$isget = parse_url($url,PHP_URL_QUERY) ? true : false;//if contain get parameters, then is get request
	
	$pdata = is_array($pdata) ? http_build_query($pdata) : $pdata;
	
	$encPass = textToBase64(encRC4(USER,PASS));//view /web/pages/function/rc4.js
	$cookie = 'username='.USER.'; rembMe=checked; uname='.USER.'; password='.$encPass;#.'; path=/';

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url );
	if(!$isget)//switch to GET request
	{
	curl_setopt($ch, CURLOPT_POSTFIELDS, $pdata);
	curl_setopt($ch, CURLOPT_POST, 1);
	}
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
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, TIMEOUT);
	$resp = curl_exec($ch);
	$info = curl_getinfo($ch);
	curl_close($ch);
	
	$body = ''; $head = array();
	$resRows = explode("\r\n",substr($resp,0, $info['header_size']));
	foreach($resRows as $row)
		$head[ current(explode(': ',$row)) ] = next(explode(': ',$row));
	//split http head in headers key=>value

	if(isset($info['request_header']))
		$reqRows = explode("\r\n", trim($info['request_header']));
	else
		$reqRows = array();

	if($info['download_content_length']>0)
		$body = substr($resp, - $info['download_content_length']);
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

?>
