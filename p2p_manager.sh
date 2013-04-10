#!/bin/sh
#
#auto de/active p2p, tramite using pulse script
#
#exit 0

#STAT=$(echo $0 | sed 's/\.sh/\.stat/g')
#LOG=$(echo $0 | sed 's/\.sh/\.log/g')
PATH=/ffp/bin:/usr/bin:/bin:/ffp/usr/local/bin
PING="ping -c2 -W2"
P2POFF="/ffp/home/root/pulse -q -o -O"
P2PON="/ffp/home/root/pulse -q -s -O"
HOSTS=/etc/hosts
#ME=$(hostname)
#DNS=$(cut -d' ' -f2 /etc/resolv.conf)
#GW=$(ip route | grep default | cut -d' ' -f3)
#IGNORE="^$\|^#\|^127\|$ME\|$DNS\|$GW\|dlink"
#IPS=$(grep -v $IGNORE $HOSTS | cut -d' ' -f1 | sort | uniq)

IPS="192.168.1.2 192.168.1.3"
#IGNORED HOSTS

#date +"%d-%m-%Y %H:%I:%S" 
date
ONLINE=0
for IP in $IPS; do
	HOST=$(grep $IP $HOSTS | cut -d' ' -f2)
	$PING $IP > /dev/null 2>&1
	if [ $? -eq 0 ]; then
		ONLINE=1
		break
	fi
done

if [ $ONLINE -eq 0 ]; then
	echo "hosts offline"
#	echo "1" > $STAT
	$P2PON
else
	echo "hosts online $HOST"
#	echo "0" > $STAT
	$P2POFF
fi
