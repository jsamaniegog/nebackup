#!/bin/bash  
HOSTNA=$1
DEVICE=$2
RANNUM=$3
SERVER=$4
SNMPRW=$5
#DATE=$(date +"%Y%m%d_%H%M")
#RANNUM=(date +"%M")

(echo -e "open ${DEVICE}\r"
sleep 1
echo -e "\badmin"
sleep 1
echo -e "${SNMPRW}"
sleep 1
echo -e "copy running-config tftp ${SERVER} ${HOSTNA}"
sleep 1) | telnet 
