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

# ccCopyProtocol: The protocol file transfer protocol that should be used to copy the configuration file over the network.
#1. tftp
#2. ftp
#3. rcp
#4. scp
#5. sftp

# ccCopySourceFileType: Specifies the type of file to copy from. The object can be:
#1. networkFile
#2. iosFile
#3. startupConfig
#4. runningConfig <---
#5. terminal
#6. fabricStartupConfig

# ccCopyDestFileType: specifies the type of file to copy to. The object can be:
#1. networkFile <---
#2. iosFile
#3. startupConfig
#4. runningConfig
#5. terminal
#6. fabricStartupConfig

#ccCopyEntryRowStatus: The status of this table entry. Once the entry status is 
#set to active, the associated entry cannot be modified until the request 
#completes (ccCopyState transitions to ‘successful’ or ‘failed’ state). The object
# can be:
#1. active
#2. notInService
#3. notReady
#4. createAndGo
#5. createAndWait
#6. destroy

snmpset -v 2c -c $SNMPRW $DEVICE   \
.1.3.6.1.4.1.9.9.96.1.1.1.1.2.$RANNUM i $PROTOC \
.1.3.6.1.4.1.9.9.96.1.1.1.1.3.$RANNUM i 4 \
.1.3.6.1.4.1.9.9.96.1.1.1.1.4.$RANNUM i 1 \
.1.3.6.1.4.1.9.9.96.1.1.1.1.5.$RANNUM a "$SERVER" \
.1.3.6.1.4.1.9.9.96.1.1.1.1.6.$RANNUM s "$HOSTNA" \
.1.3.6.1.4.1.9.9.96.1.1.1.1.7.$RANNUM s "$USERNA" \
.1.3.6.1.4.1.9.9.96.1.1.1.1.8.$RANNUM s "$PASSWD" \
.1.3.6.1.4.1.9.9.96.1.1.1.1.14.$RANNUM i 4

