#!/bin/sh
#
# require: zenity command line
#
#exit 0

zenity --question --text "Shutdown Host?" && pulse -fS

