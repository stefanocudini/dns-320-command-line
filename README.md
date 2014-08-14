#COMMAND-LINE INTERFACE for D-LINK SHARECENTER DNS-320

Only for DLINK Firmware 2.03

![Image](https://raw2.github.com/stefanocudini/dns-320-command-line/master/dns-320-pulse.png)

Copyleft Stefano Cudini 2012

stefano.cudini@gmail.com

[labs.easyblog.it](http://labs.easyblog.it/dns-320-command-line/)

**Suggestions:**

This script is designed to run on a remote host than your NAS, but enabling ssh access to the NAS, eg using [Fun Plug](http://dns323.kood.org/howto:ffp), you can copy the script into the NAS and run it from the command line via ssh or crond

**Requirements:**
* php5-cli

**Recommends:**
* php5-curl

**Usage**

```

Usage: pulse options [host[:port]]

       host                        hostname or ip target, default: pulse
       port                        http port number, default: 80

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
       -T,--temperature            get temperature inside device
       -A,--fan[=off|low|high]     get or set fan mode
       -u,--ups                    get ups state
       -t,--time                   get date and time of nas       
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

```

**Output example p2p list:**
```
$ ./pulse -p
	P2P: On
	 Speed:  64.2 KBps / 46.5 KBps
	 Limits: -1 KBps / 20 KBps
	 AutoDownload: Off
	 Torrents: 7
	  Download  38%   251.5MB of 661.9MB          0.0 / 1.6 KBps   #7  Elephants Dream BDRip...
	  Stopped   34%   138.6MB of 407.7MB          0.0 / 0.0 KBps   #5  Kubuntu Linux.iso 64b...
	  Stopped   29%      47MB of 1.45GB           0.0 / 0.0 KBps   #4  Sintel 2010 1080p Xvi...
	  Stopped   15%   261.1MB of 1.7GB            0.0 / 0.0 KBps   #1  PBig Buck Bunny 720p ...
	  Download  10%   153.6MB of 1.5GB   19h:4m  24.3 / 37.0 KBps  #3  Debian 6 0 i386 - CD1...
	  Download  5%     24.4MB of 658.0MB  4h:3m  37.8 / 1.2 KBps   #2  Debian 6 0 i386 - CD2...
	  Stopped   0%         0B of 710.0MB          0.0 / 0.0 KBps   #6  Debian 6 0 i386 - CD3...
```
