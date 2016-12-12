#!/bin/bash
HOSTNA=$1
DEVICE=$2
RANNUM=$3
SERVER=$4
SNMPRW=$5

snmpwalk -v 2c -c $SNMPRW $DEVICE   \
.1.3.6.1.4.1.9.9.96.1.1.1.1.10.$RANNUM

