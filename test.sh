#!/bin/bash

controller="aaa.bbb.com"
timeout="800"
retry="1"
maxproc="24"
devnet="192.168.1.0/24"
devices="ap1.local ap10.aaa.bb index.hu 192.168.1.1 192.168.1.111"
resolvdup="1"

export controller
export timeout
export retry
export maxproc
export devnet
export devices
export resolvdup

php ubnt_unifi.php debug

