#!/bin/bash
HOSTNA=$1
DEVICE=$2
RANNUM=$3
SERVER=$4
SNMPRW=$5
#DATE=$(date +"%Y%m%d_%H%M")
#RANNUM=(date +"%M")

snmpset -v 2c -c $SNMPRW $DEVICE   \
.1.3.6.1.4.1.9.9.96.1.1.1.1.2.$RANNUM i 1 \
.1.3.6.1.4.1.9.9.96.1.1.1.1.3.$RANNUM i 4 \
.1.3.6.1.4.1.9.9.96.1.1.1.1.4.$RANNUM i 1 \
.1.3.6.1.4.1.9.9.96.1.1.1.1.5.$RANNUM a "$SERVER" \
.1.3.6.1.4.1.9.9.96.1.1.1.1.6.$RANNUM s "$HOSTNA" \
.1.3.6.1.4.1.9.9.96.1.1.1.1.14.$RANNUM i 4

