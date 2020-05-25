#!/bin/bash

controller="aaa.bbb.com"
timeout="800"
retry="1"
maxproc="24"
devnet="192.168.1.0/32"
devices="ap1.local ap1.local 192.168.1.1 192.168.1.111"

export controller
export timeout
export retry
export maxproc
export devnet
export devices

php ubnt_unifi.php debug

