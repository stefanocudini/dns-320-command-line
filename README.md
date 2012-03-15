SIMPLE COMMAND-LINE INTERFACE for D-LINK SHARECENTER DNS-320

Copyleft Stefano Cudini 2012
stefano.cudini@gmail.com

requirements:
php5-cli
php5-curl

Usage: pulse.php OPTIONS [host[:port]]
       host                    hostname or ip target, default: pulse
       port                    port number for host, default: 80
       -p,--p2p[=on|off]       get or set p2p client state
       -c,--p2p-clear          clear p2p complete list
       -D,--download[=url]     list or add url in http downloader
       -C,--download-clear     clear complete http downloads list
       -t,--temp               get temperature inside
       -f,--fan=[off|low|high] get or set fan mode
       -u,--ups                get ups state
       -d,--disks              get disks usage
       -s,--shutdown           power off the system
       -r,--restart            restart the system
       -h,--help               print this help


