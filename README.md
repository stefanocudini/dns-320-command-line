SIMPLE COMMAND-LINE INTERFACE for D-LINK SHARECENTER DNS-320

Copyleft Stefano Cudini 2012
stefano.cudini@gmail.com

requirements:
php5-cli
recommends:
php5-curl

Some features:
get or set p2p client state, 
clear p2p complete list, 
list or add url in http downloader, 
download multiple urls from file list,
clear complete http downloads list, 
get temperature, 
get or set fan mode, 
get ups state, 
get usb disk info,
umount usb disk,
get disks usage, 
get nfs shares,
get or set ftp state,
shutdown and restart system

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
