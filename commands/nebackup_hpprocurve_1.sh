#!/bin/bash  
HOSTNA=$1
DEVICE=$2
RANNUM=$3
SERVER=$4
SNMPRW=$5
PROTOC=$6
USERNA=$7
PASSWD=$8
#DATE=$(date +"%Y%m%d_%H%M")
#RANNUM=(date +"%M")

(echo -e "open ${DEVICE}\r"
sleep 1
echo -e "\b${USERNA}"
sleep 1
echo -e "${PASSWD}"
sleep 1
echo -e "copy running-config tftp ${SERVER} ${HOSTNA}"
sleep 1) | telnet 2>&1
