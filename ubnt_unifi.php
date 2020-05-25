#!/usr/bin/php
<?php

// Munin monitoring plugin for Ubiquiti Unifi AP system.

//$controller = "unifi.company.com";
//$hosts = "ap01.wireless.company.lan ap02.wireless.company.lan ap03.wireless.company.lan ap04.wireless.company.lan";


$controller = getenv('controller');
$hosts = getenv('devices');
$timeout = getenv('timeout');
$retry = getenv('retry');
$maxproc = getenv('maxproc');
$devnetw = getenv('devnet');
$resolvdup = getenv('resolvdup');

$replace_chars = array("\"","\$","@","^","`",",","|","%",";",".","~","(",")","/","\\","{","}",":","?","[","]","=","+","#","!","-",);	// Special chars replace

function print_header($inp){ // prints Munin-config data from processed data array

	$cf  = "multigraph unifi_".$inp['g_multi']."\n";
        $cf .= "host_name ".$inp['g_controller']."\n";
        $cf .= "graph_title ".$inp['g_title']."\n";
        $cf .= "graph_args --base 1000 \n";
        $cf .= "graph_vlabel ".$inp['g_vlabel']."\n";
        $cf .= "graph_category ".$inp['g_category']."\n";
        $cf .= "graph_info ".$inp['g_info']."\n";
	if(isset($inp['g_order'])){ $cf .= "graph_order ".$inp['g_order']."\n"; }

	foreach($inp['head'] as $key => $val){
		$cf .= "unifi_".$val['name'].".label ".$val['label']."\n";
		$cf .= "unifi_".$val['name'].".draw  ".$val['draw']."\n";
		$cf .= "unifi_".$val['name'].".info  ".$val['info']."\n";
		if(isset($val['type'])){ $cf .= "unifi_".$val['name'].".type  ".$val['type']."\n"; }
		if(isset($val['min'])) { $cf .= "unifi_".$val['name'].".min  ".$val['min']."\n";  }
		if(isset($val['cdef'])){ $cf .= "unifi_".$val['name'].".cdef  ".$val['cdef']."\n"; }
		if(isset($val['graph'])){ $cf .= "unifi_".$val['name'].".graph  ".$val['graph']."\n"; }
		if(isset($val['max'])){ $cf .= "unifi_".$val['name'].".max  ".$val['max']."\n"; }
		if(isset($val['negative'])){ $cf .= "unifi_".$val['name'].".negative  ".$val['negative']."\n"; }
	}
	$cf .= "\n";
	echo iconv("UTF-8", "ISO-8859-2", $cf), PHP_EOL;
}


function print_data($inp) {

        if(!array_key_exists('data', $inp) or !array_key_exists('g_multi', $inp)){
                return;
        }
	
	$pf  = "multigraph unifi_".$inp['g_multi']."\n";
	foreach($inp['data'] as $key => $val){
		$pf .= "unifi_".$val['name'].".value ".$val['value']."\n" ;
	}
	$pf .= "\n";
	
	echo iconv("UTF-8", "ISO-8859-2", $pf), PHP_EOL;

}

function count_wl_networks($inp){ //Count wireless networks from interfade data
	$num = -10;
        foreach($inp as $key => $val){          // jump to the end of wl interface list
		if(strpos($key, "iso.3.6.1.4.1.41112.1.6.1.2.1.1.") !== false){
                	if(is_numeric(explode(": ", $val)[1]) and $num < explode(": ", $val)[1]){
                        	$num = explode(": ", $val)[1];
                        }
                }
	}
        $num = $num+1;	//Because the snmp counts from 0
	
	if($num > 0){
		return $num;
	} else {
		return -1;
	}
}


function collect_radio_summary($inp,$host){
	global $controller, $replace_chars;
	$ret = array();

	if(isset($host) and $host !== null and $host != "" ){
	        if(!array_key_exists($host, $inp)){
                   return $ret;
	        }
                $ret['g_multi'] = "radio_".str_replace( array(".", ":"), "_" ,$controller).".".str_replace( array(".", ":"), "_" ,$host);
		$ret['g_controller'] = $controller;
		$location = str_replace("\"", "", explode(": ", $inp[$host]["iso.3.6.1.2.1.1.6.0"])[1]);
        if( $location != "Unknown" and $location != "" ){ $ret['g_title'] = "Unifi Clients on: ".$location ; } // if the Location is not filled in Controller settings, use the hostname or ip address
		else { $ret['g_title'] = "Unifi Clients on: ".$host; };
                $ret['g_vlabel'] = "Users";
                $ret['g_category'] = "wl_clients_ap";
                $ret['g_info'] = "ubnt_wireless";
	} else {
		$ret['g_multi'] = "radio_".str_replace( array(".", ":"), "_" ,$controller);
                $ret['g_controller'] = $controller;
		$ret['g_title'] = "Unifi Clients on: $controller (total)";
		$ret['g_vlabel'] = "Users";
		$ret['g_category'] = "Wl_clients_all";
		$ret['g_info'] = "ubnt_wireless";
	}
				
        $ret['head'][0]['name'] = "sum_clients";
        $ret['head'][0]['label'] = "Total clients";
        $ret['head'][0]['draw'] = "LINE1.2";
        $ret['head'][0]['info'] = "Total Clients";
        $ret['head'][0]['type'] = "GAUGE";
        $ret['head'][0]['min']	= "0";

        $ret['head'][1]['name'] = "2g_clients";
        $ret['head'][1]['label'] = "2.4Ghz";
        $ret['head'][1]['draw'] = "LINE1.2";
        $ret['head'][1]['info'] = "2.4Ghz Clients";
        $ret['head'][1]['type'] = "GAUGE";
        $ret['head'][1]['min']  = "0";

        $ret['head'][2]['name'] = "5g_clients";
        $ret['head'][2]['label'] = "5Ghz";
        $ret['head'][2]['draw'] = "LINE1.2";
        $ret['head'][2]['info'] = "2.4Ghz Clients";
        $ret['head'][2]['type'] = "GAUGE";
        $ret['head'][2]['min']  = "0";


	if(isset($host) and $host !== null and $host != "" ){	// trim raw data array to current device (in $host) or use the whole array when calculating controller's data 
		$temp = $inp;
		unset($inp);
		$inp = array($host => $temp[$host]);
		unset($temp);
	}

	foreach($inp as $key => $val){

		for($i=1; $i<=count_wl_networks($inp[$key]); $i++){	//Collect clients by band.
			if( explode(": ", $inp[$key]["iso.3.6.1.4.1.41112.1.6.1.2.1.4.".$i])[1] < 15 ){					//2.4Ghz client
				$ret['data'][1]['name'] = "2g_clients";
				@$ret['data'][1]['value'] += explode(": ", $inp[$key]["iso.3.6.1.4.1.41112.1.6.1.2.1.8.".$i])[1];
			} else {													// 5Ghz clients
                $ret['data'][2]['name'] = "5g_clients";
                @$ret['data'][2]['value'] += explode(": ", $inp[$key]["iso.3.6.1.4.1.41112.1.6.1.2.1.8.".$i])[1];
			}
		}
                $ret['data'][0]['name'] = "sum_clients";
                $ret['data'][0]['value'] = $ret['data'][1]['value'] + $ret['data'][2]['value'];



                for($i=1; $i<=count_wl_networks($inp[$key]); $i++){     //Collect clients by SSID.

                        foreach($ret['data'] as $key2 => $val2){	//find if ssid is already used
                                if($ret['data'][$key2]['name'] == str_replace($replace_chars, "_", explode(": ", $inp[$key]["iso.3.6.1.4.1.41112.1.6.1.2.1.6.".$i])[1]) ){
                                        break;
                                }
                        }
					//found, update record
                        if($ret['data'][$key2]['name'] == str_replace($replace_chars, "_", explode(": ", $inp[$key]["iso.3.6.1.4.1.41112.1.6.1.2.1.6.".$i])[1]) ){
                                $ret['data'][$key2]['value'] += explode(": ", $inp[$key]["iso.3.6.1.4.1.41112.1.6.1.2.1.8.".$i])[1];

                        } else {	//not found, new record
                                $ret['data'][] = array( "name" => str_replace($replace_chars, "_", explode(": ", $inp[$key]["iso.3.6.1.4.1.41112.1.6.1.2.1.6.".$i])[1]),
                                                        "value" => explode(": ", $inp[$key]["iso.3.6.1.4.1.41112.1.6.1.2.1.8.".$i])[1] 
                                                );
                                $ret['head'][] = array( "name" => str_replace($replace_chars, "_", explode(": ", $inp[$key]["iso.3.6.1.4.1.41112.1.6.1.2.1.6.".$i])[1]),
                                                        "label" => str_replace("\"", "",explode(": ", $inp[$key]["iso.3.6.1.4.1.41112.1.6.1.2.1.6.".$i])[1] ),
                                                        "draw"  => "LINE1.2",
                                                        "info"  => explode(": ", $inp[$key]["iso.3.6.1.4.1.41112.1.6.1.2.1.6.".$i])[1],
                                                        "type"	=> "GAUGE",
                                                        "min"	=> "0",
                                                );

                        }
                }
        }


return $ret;
}




function collect_netw_summary($inp,$host){	//network information
        global $controller, $replace_chars;
        $ret = array();
	
	if(isset($host) and $host !== null and $host != "" ){ // When showing the Ap's summary.
	      if(!array_key_exists($host, $inp)){
                return $ret;
	      }
              $temp = $inp;
              unset($inp);
              $inp = array($host => $temp[$host]);
              unset($temp);
              $multiplier = 8;
              $divider = 1;
      	} else {			
              $multiplier = 8*1024*4;	// When showing the controller's summary
              $divider = 1024*4;	//because the normal INTEGER(32) would be overflowed
      	}


        if(isset($host) and $host !== null and $host != "" ){
                $ret['g_multi'] = "netw_".str_replace( array(".", ":"), "_" ,$controller).".".str_replace( array(".", ":"), "_" ,$host);
                $ret['g_controller'] = $controller;
                $location = str_replace("\"", "", explode(": ", $inp[$host]["iso.3.6.1.2.1.1.6.0"])[1]);
		if($location != "Unknown" and $location != ""){ $ret['g_title'] = "Network Usage on: ".$location; }
                else{$ret['g_title'] = "Network Usage on: ".$host;}
                $ret['g_vlabel'] = "bits in(-) / out(+) per second";
                $ret['g_category'] = "Wl_netw_ap";
                $ret['g_info'] = "ubnt_network";
                $ret['g_order'] = "unifi_rx_all unifi_tx_all unifi_rx_2g unifi_tx_2g unifi_rx_5g unifi_tx_5g";

        } else {
                $ret['g_multi'] = "netw_".str_replace( array(".", ":"), "_" ,$controller);
                $ret['g_controller'] = $controller;
                $ret['g_title'] = "Netwok Usage on: $controller (total)";
                $ret['g_vlabel'] = "bits in(-) / out(+) per second";
                $ret['g_category'] = "Wl_netw_all";
                $ret['g_info'] = "ubnt_network";
                $ret['g_order'] = "unifi_rx_all unifi_tx_all unifi_rx_2g unifi_tx_2g unifi_rx_5g unifi_tx_5g";
        }
         
                $ret['head'][0]['name'] = "rx_all";
                $ret['head'][0]['label'] = "RxTotal (bps)";
                $ret['head'][0]['draw'] = "LINE1.2";
                $ret['head'][0]['info'] = "Total Received";
                $ret['head'][0]['type'] = "DERIVE";
                $ret['head'][0]['min']  = "0";
                $ret['head'][0]['graph']  = "no";
                $ret['head'][0]['cdef']  = "unifi_rx_all,$multiplier,*";
                $ret['head'][0]['max']  = "1000000000";

                $ret['head'][1]['name'] = "tx_all";
                $ret['head'][1]['label'] = "Total (bps)";
                $ret['head'][1]['draw'] = "LINE1.2";
                $ret['head'][1]['info'] = "Total Sent";
                $ret['head'][1]['type'] = "DERIVE";
                $ret['head'][1]['min']  = "0";
                $ret['head'][1]['cdef']  = "unifi_tx_all,$multiplier,*";
                $ret['head'][1]['max']  = "1000000000";
                $ret['head'][1]['negative']  = "unifi_rx_all";

                $ret['head'][2]['name'] = "rx_2g";
                $ret['head'][2]['label'] = "2G (bps)";
                $ret['head'][2]['draw'] = "LINE1.2";
                $ret['head'][2]['info'] = "Total Received";
                $ret['head'][2]['type'] = "DERIVE";
                $ret['head'][2]['min']  = "0";
                $ret['head'][2]['graph']  = "no";
                $ret['head'][2]['cdef']  = "unifi_rx_2g,$multiplier,*";
                $ret['head'][2]['max']  = "1000000000";

                $ret['head'][3]['name'] = "tx_2g";
                $ret['head'][3]['label'] = "2G (bps)";
                $ret['head'][3]['draw'] = "LINE1.2";
                $ret['head'][3]['info'] = "Total Sent";
                $ret['head'][3]['type'] = "DERIVE";
                $ret['head'][3]['min']  = "0";
                $ret['head'][3]['cdef']  = "unifi_tx_2g,$multiplier,*";
                $ret['head'][3]['max']  = "1000000000";
                $ret['head'][3]['negative']  = "unifi_rx_2g";

                $ret['head'][4]['name'] = "rx_5g";
                $ret['head'][4]['label'] = "5G (bps)";
                $ret['head'][4]['draw'] = "LINE1.2";
                $ret['head'][4]['info'] = "Total Received";
                $ret['head'][4]['type'] = "DERIVE";
                $ret['head'][4]['min']  = "0";
                $ret['head'][4]['graph']  = "no";
                $ret['head'][4]['cdef']  = "unifi_rx_5g,$multiplier,*";
                $ret['head'][4]['max']  = "1000000000";

                $ret['head'][5]['name'] = "tx_5g";
                $ret['head'][5]['label'] = "5G (bps)";
                $ret['head'][5]['draw'] = "LINE1.2";
                $ret['head'][5]['info'] = "Total Sent";
                $ret['head'][5]['type'] = "DERIVE";
                $ret['head'][5]['min']  = "0";
                $ret['head'][5]['cdef']  = "unifi_tx_5g,$multiplier,*";
                $ret['head'][5]['max']  = "1000000000";
                $ret['head'][5]['negative']  = "unifi_rx_5g";


        foreach($inp as $key => $val){

                for($i=1; $i<=count_wl_networks($inp[$key]); $i++){     //Collect netw_bytes by band and direction.
                        if( explode(": ", $inp[$key]["iso.3.6.1.4.1.41112.1.6.1.2.1.4.".$i])[1] < 15 ){                                 //2.4Ghz client
                                $ret['data'][2]['name'] = "rx_2g";		       
                                @$ret['data'][2]['value'] += round((explode(": ", $inp[$key]["iso.3.6.1.4.1.41112.1.6.1.2.1.10.".$i])[1]) / $divider);
                                $ret['data'][3]['name'] = "tx_2g";
                                @$ret['data'][3]['value'] += round((explode(": ", $inp[$key]["iso.3.6.1.4.1.41112.1.6.1.2.1.16.".$i])[1]) / $divider);

                        } else {                                                                                                        // 5Ghz clients
                                $ret['data'][4]['name'] = "rx_5g";                     
                                @$ret['data'][4]['value'] += round((explode(": ", $inp[$key]["iso.3.6.1.4.1.41112.1.6.1.2.1.10.".$i])[1]) / $divider);
                                $ret['data'][5]['name'] = "tx_5g";
                                @$ret['data'][5]['value'] += round((explode(": ", $inp[$key]["iso.3.6.1.4.1.41112.1.6.1.2.1.16.".$i])[1]) / $divider);
                        }
                }
                $ret['data'][0]['name'] = "rx_all";
                $ret['data'][0]['value'] = $ret['data'][2]['value'] + $ret['data'][4]['value'];
                $ret['data'][1]['name'] = "tx_all";
                $ret['data'][1]['value'] = $ret['data'][3]['value'] + $ret['data'][5]['value'];



                for($i=1; $i<=count_wl_networks($inp[$key]); $i++){     //Collect netw_bytes by SSID.

                        foreach($ret['data'] as $key2 => $val2){        //find if ssid is already used
                                if($ret['data'][$key2]['name'] == "rx_".str_replace($replace_chars, "_", explode(": ", $inp[$key]["iso.3.6.1.4.1.41112.1.6.1.2.1.6.".$i])[1]) ){
                                        break;
                                }
                        }
                                        //ssid found, update record
                        if($ret['data'][$key2]['name'] == "rx_".str_replace($replace_chars, "_", explode(": ", $inp[$key]["iso.3.6.1.4.1.41112.1.6.1.2.1.6.".$i])[1]) ){
                                $ret['data'][$key2]['value'] += round((explode(": ", $inp[$key]["iso.3.6.1.4.1.41112.1.6.1.2.1.10.".$i])[1]) / $divider);

                        } else {       //ssid not found, new record
                                $ret['data'][] = array( "name" => "rx_".str_replace($replace_chars, "_", explode(": ", $inp[$key]["iso.3.6.1.4.1.41112.1.6.1.2.1.6.".$i])[1]),
                                                        "value" => round((explode(": ", $inp[$key]["iso.3.6.1.4.1.41112.1.6.1.2.1.10.".$i])[1]) / $divider) ,
                                                );
                                $ret['head'][] = array( "name"  => "rx_".str_replace($replace_chars, "_", explode(": ", $inp[$key]["iso.3.6.1.4.1.41112.1.6.1.2.1.6.".$i])[1]),
                                                        "label" => "RX_".str_replace("\"", "",explode(": ", $inp[$key]["iso.3.6.1.4.1.41112.1.6.1.2.1.6.".$i])[1] )." (bps)",
                                                        "draw"  => "LINE1.2",
                                                        "info"  => "Rx_".explode(": ", $inp[$key]["iso.3.6.1.4.1.41112.1.6.1.2.1.6.".$i])[1],
                                                        "type"  => "DERIVE",
                                                        "min"   => "0",
                                                        "graph"	=> "no",
                                                        "cdef"	=> "unifi_rx_".str_replace($replace_chars, "_", explode(": ", $inp[$key]["iso.3.6.1.4.1.41112.1.6.1.2.1.6.".$i])[1]).",$multiplier,*",
                                                        "max"	=> "1000000000",
                                                );

                        }

			foreach($ret['data'] as $key2 => $val2){        //find if ssid is already used
                    		if($ret['data'][$key2]['name'] == "tx_".str_replace($replace_chars, "_", explode(": ", $inp[$key]["iso.3.6.1.4.1.41112.1.6.1.2.1.6.".$i])[1]) ){
                            		break;
                    		}
            		}
                     		       //ssid found, update record
            		if($ret['data'][$key2]['name'] == "tx_".str_replace($replace_chars, "_", explode(": ", $inp[$key]["iso.3.6.1.4.1.41112.1.6.1.2.1.6.".$i])[1]) ){
                    		$ret['data'][$key2]['value'] += round((explode(": ", $inp[$key]["iso.3.6.1.4.1.41112.1.6.1.2.1.16.".$i])[1]) / $divider);

			} else {       //ssid not found, new record
                    		$ret['data'][] = array( "name" => "tx_".str_replace($replace_chars, "_", explode(": ", $inp[$key]["iso.3.6.1.4.1.41112.1.6.1.2.1.6.".$i])[1]),
                                			"value" => round((explode(": ", $inp[$key]["iso.3.6.1.4.1.41112.1.6.1.2.1.16.".$i])[1]) / $divider) ,
                                            );
                    		$ret['head'][] = array( "name"  => "tx_".str_replace($replace_chars, "_", explode(": ", $inp[$key]["iso.3.6.1.4.1.41112.1.6.1.2.1.6.".$i])[1]),
                        				"label" => "".str_replace("\"", "",explode(": ", $inp[$key]["iso.3.6.1.4.1.41112.1.6.1.2.1.6.".$i])[1] )." (bps)",
                                        	    	"draw"  => "LINE1.2",
                                            		"info"  => "Tx_".explode(": ", $inp[$key]["iso.3.6.1.4.1.41112.1.6.1.2.1.6.".$i])[1],
                                	            	"type"  => "DERIVE",
                                        	    	"min"   => "0",
                                            		"cdef"  => "unifi_tx_".str_replace($replace_chars, "_", explode(": ", $inp[$key]["iso.3.6.1.4.1.41112.1.6.1.2.1.6.".$i])[1]).",$multiplier,*",
                                            		"max"   => "1000000000",
                                                    "negative" => "unifi_rx_".str_replace($replace_chars, "_", explode(": ", $inp[$key]["iso.3.6.1.4.1.41112.1.6.1.2.1.6.".$i])[1]),
                                            );
            		}


                }
        }


return $ret;
}


function collect_response_time($inp,$host){			//Calculates the response_time array from raw data
        global $controller, $replace_chars, $hosts;
        $ret = array();

        if(isset($host) and $host !== null and $host != "" ){
                if(!array_key_exists($host, $inp)){
                   return $ret;
                }
                $ret['g_multi'] = "ping_".str_replace( array(".", ":"), "_" ,$controller).".".str_replace( array(".", ":"), "_" ,$host);
                $ret['g_controller'] = $controller;
                $location = str_replace("\"", "", explode(": ", $inp[$host]["iso.3.6.1.2.1.1.6.0"])[1]);
		if( $location != "Unknown" and $location != "" ){ $ret['g_title'] = "AP response time on: ".$location ; } // if the Location is not filled in Controller settings, use t
                else { $ret['g_title'] = "AP response time on: ".$host; };
                $ret['g_vlabel'] = "Roundtrip time (seconds)";
                $ret['g_category'] = "wl_ping_ap";
                $ret['g_info'] = "ubnt_wireless";
//		$ret['g_order'] = 'unifi_ping_time';
        } else {
                $ret['g_multi'] = "ping_".str_replace( array(".", ":"), "_" ,$controller);
                $ret['g_controller'] = $controller;
                $ret['g_title'] = "AP response time on: $controller (all)";
                $ret['g_vlabel'] = "Roundtrip time (seconds)";
                $ret['g_category'] = "Wl_ping_all";
                $ret['g_info'] = "ubnt_wireless";	
		$ret['g_order'] = '';
	        foreach($hosts as $key => $val){		//prints the host names on summary
        		$ret['g_order'] .= "unifi_ping_".str_replace( array(".", ":"), "_" ,$val)." ";
        	}
	}



	if(isset($host) and $host !== null and $host != "" ){   // chunks the raw data to the current AP's data only
		$temp = $inp;
		unset($inp);
		$inp = array($host => $temp[$host]);
		unset($temp);
        } 


	foreach($inp as $key => $val){

//		if( (isset($host) and $host !== null and $host != "") ){	//Replace label's text on AP's graph
//			$key = "time";
//			$label= "Response Time";
//		} else {
//			$label = $key;
//		}
		$ret['head'][$key]['name'] = "ping_".str_replace( array(".", ":"), "_" ,$key);
		$ret['head'][$key]['label'] = $key;
		$ret['head'][$key]['draw'] = "LINE1.2";
		$ret['head'][$key]['info'] = "Response Time";
		$ret['head'][$key]['type'] = "GAUGE";
		$ret['head'][$key]['min']  = "0";

		$ret['data'][$key]['name'] = "ping_".str_replace( array(".", ":"), "_" ,$key);
		$ret['data'][$key]['value'] = round($val["response_time"], 7);        
	}

return $ret;
}



/********************* START *******************/


$hosts = explode(" ", $hosts);
$netw = @explode('/', $devnetw)[0];
$mask = @explode('/', $devnetw)[1];
$hosts2 = array();
$hostsip = array();

if($mask != 0 ){
	for($i=1; $i<(1 << (32 - $mask)); $i++ ){		// fetch ip addresses from given network and mask
		$hosts2[] = long2ip((ip2long($netw) & ~((1 << (32 - $mask)) -1))  +$i  ) ;
	}
}

foreach($hosts as $key => $val){			// delete addresses which are given by hostname

        $hostsip[$key] = array();
        $hostsip[$key]['hs'] = $val;
        putenv('RES_OPTIONS="retrans:1 retry:1 timeout:1 attempts:1"');   //faster name resolving ?
        $hostsip[$key]['ip'] = gethostbyname($val);                     /* Returns unmodified string, when error, or ip input */

        if($hostsip[$key]['ip'] == $hostsip[$key]['hs'] && filter_var($val, FILTER_VALIDATE_IP) == ""){    /* if non ip is returned by gethost  */      
                $hostsip[$key]['ip'] = "";                                      //Delete, if name resolving error
        }                                                               
        
        if(isset($resolvdup) && $resolvdup == "1"){                     //Clarify duplicated devices
	        if(in_array($hostsip[$key]['ip'], $hosts2)){
                        unset($hosts2[ array_keys($hosts2, $hostsip[$key]['ip'])[0] ]);
	        }
        }
}
$i = count($hostsip);
foreach($hosts2 as $key2 => $val2){     // Merge hosts into one
        $hostsip[$i] = array();
        $hostsip[$i]['hs'] = $val2;
        $hostsip[$i]['ip'] = $val2;
        $i++;
}

//$hosts = array_merge($hosts, $hosts2);
unset($hosts2);
$numhost = count($hostsip);
$shm_key = ftok($argv[0], 'c');
$shm = shmop_open($shm_key, "c", 0640, ceil($numhost/$maxproc)*32768);
$sf = sem_get($shm_key,1,0640,1);
$child = array();
$raw = array();
$hostsipr = $hostsip;
unset($hostsip);
$hostsipt = array();

for($i=0,$j=0; $i<$numhost; $i++){	//Sorting addresses for child-processes
	
	if($j >= $maxproc){		//With permutation
	    $j = 0;
	}
	if(array_key_exists($j, $hostsipt) === FALSE){
                //$hostst[$j] = array();
                $hostsipt[$j] = array();
	}
        //$hostst[$j] = array_merge($hostst[$j], array($hostsr[$i]));    //With permutation
        $hostsipt[$j] = array_merge($hostsipt[$j], array($hostsipr[$i]));    //With permutation
	$j++;
}



for ($p=0; $p<$maxproc; $p++){		//Starts child processes to retrieve SNMP data.
	
	unset($hostsip);
	$hostsip = array();
	if(array_key_exists($p,$hostsipt)){
                //$hosts = $hostst[$p];
                $hostsip = $hostsipt[$p];
	}

        $pid = pcntl_fork();

        if($pid == -1){
             die('could not fork');

        } else if($pid){			// we are the parent process
                $child[$p] = $pid;

        } else {			// child
		$raw=array();
		foreach($hostsip as $key => $val){			// get raw snmp data from unifi devices
			if($hostsip[$key]['ip'] == ""){
				unset($hostsip[$key]);
			}
			if($val['hs'] != "") {
				$begin = microtime(TRUE);
				$raw[$val['hs']] = @snmp2_real_walk($val['ip'], "public", ".1.3.6.1.4.1.41112.1.6.1.2.1", $timeout*1000, $retry ); 		// wl network info
				//$raw[$val['hs']]["response_time"] = abs($begin - microtime(TRUE));							// If we count the time of the first response
				$raw[$val['hs']]["iso.3.6.1.2.1.1.6.0"] = @snmp2_get($val['ip'], "public", ".1.3.6.1.2.1.1.6.0", $timeout*1000, $retry ) ;	// location info
				$raw[$val['hs']]["iso.3.6.1.2.1.1.1.0"] = @snmp2_get($val['ip'], "public", ".1.3.6.1.2.1.1.1.0", $timeout*1000, $retry ) ;	// descr. info
				$raw[$val['hs']]["response_time"] = abs($begin - microtime(TRUE));  							// Or the time of all responses.
			}
		        if( !isset($raw[$val['hs']]["iso.3.6.1.4.1.41112.1.6.1.2.1.1.1"]) ){ // Check if AP is alive
                                unset($raw[$val['hs']]);
                                unset($hostsip[$key]);
		        }
			
			$null="";
			for($f=0; $f<(32768 - strlen(@json_encode($raw))); $f++){ //Because the json_decode() error, clear the remain parts.
				$null .= "\0";   	
			}

			sem_acquire($sf);				//Get the seamphore
			while(ord(shmop_read($shm, 0, 0)) ) {continue;} //waiting for master to pull the data
			shmop_write($shm, @json_encode($raw).$null, 0);
			sem_release($sf);
		}
	        exit;
	}

}
unset($hostsipt);
unset($hostsip);

function numchild($child, $n){	//How many child process is alive
        $l=0;
        for($i=0; $i<$n; $i++){
            if($child[$i] > 0){
                $l++;
            }
        }
        return $l;
}


while(numchild($child, $maxproc)){	//Receive the raw data segments and wait for child processes

	for($p=0; $p<$maxproc; $p++){
		if( abs($pid = pcntl_waitpid($child[$p], $status, WNOHANG)) > 0) {//Protect against Zombie children
			$child[$p] = 0;
		}
        }

	$ret = shmop_read($shm, 0, 0);  //Read from shared memory
	if(ord($ret)){
                $ret = preg_replace('/[[:cntrl:]]/', '', $ret);         // for json_decode
                $raw = @array_merge($raw, @json_decode($ret, true));      
                shmop_write($shm,"\0\0\0\0\0", 0);
	}
	usleep(1);	//Less cpu load
}

sem_remove($sf);
shmop_delete($shm);
shmop_close($shm);
$hostsip = $hostsipr;
//print_r($raw);
//print_r($hostsip);
//$test = collect_netw_summary($raw, "ap12.wireless.lan");
//print_r($test);
//$test = collect_response_time($raw, "ap12.wireless.lan");
//print_r($test);

if(!is_array($raw) /*|| empty($raw)*/){     
        die();
}


if (isset($argv[1]) and $argv[1] == "config"){			// munin config

	print_header(collect_radio_summary($raw,null));
	foreach($hostsip as $key => $val){
		print_header(collect_radio_summary($raw,$key['hs']));
	}
        print_header(collect_netw_summary($raw,null));
        foreach($hostsip as $key => $val){
                print_header(collect_netw_summary($raw,$key['hs']));
        } 
	print_header(collect_response_time($raw,null));
        foreach($hostsip as $key => $val){
                print_header(collect_response_time($raw,$key['hs']));
        }

} else if(isset($argv[1]) and $argv[1] == "debug"){
	echo "\n\n DEBUG INFORMATION FROM munin_unifi \n\n";
	echo "Configurations:\n";
	echo "\tController: ".$controller."\n";
	echo "\tTimeout: ".$timeout."\n";
	echo "\tRetry: ".$retry."\n";
	echo "\tMaxproc: ".$maxproc."\n";
	echo "\tDevices_network: ".$devnetw."\n";
        echo "\tDevice_hosts: \n";
        foreach($hostsip as $key => $val){
                printf("\t\tIP: %24s\tHost: %24s\n", $val['ip'], $val['hs']);
        }

	echo "\nInternal: \n";
        echo "\tShared_key: ".$shm_key."\n";
        echo "\tShared_mem_key: ".$shm."\n";
        echo "\tSemaphore_key: ".$sf."\n";
        echo "\tFunction_exist(ftok): ".function_exists("ftok")."\n";
        echo "\tFunction_exist(shmop_open): ".function_exists("shmop_open")."\n";
        echo "\tFunction_exist(sem_get): ".function_exists("sem_get")."\n";
        echo "\tFunction_exist(pcntl_fork): ".function_exists("pcntl_fork")."\n";
        echo "\tFunction_exist(pcntl_waitpid): ".function_exists("pcntl_waitpid")."\n";
        echo "\tFunction_exist(json_encode): ".function_exists("json_encode")."\n";
        echo "\tFunction_exist(snmp2_get): ".function_exists("snmp2_get")."\n";

	echo "\n\nRAW_data: \n";
	print_r($raw);

	echo "\nCollected infos on Controller:\n";
	print_r(collect_radio_summary($raw,null));
	print_r(collect_netw_summary($raw,null));
        print_r(collect_response_time($raw,null));
        
        echo "\n\nHeader-Print test on Controller: \n\n";
        print_header(collect_radio_summary($raw,null));
        echo "\n\nData-Print test on Controller: \n\n";
        print_data(collect_radio_summary($raw,null));


} else {							// munin data
	print_data(collect_radio_summary($raw,null));
        foreach($hostsip as $key => $val){
                print_data(collect_radio_summary($raw,$val['hs']));
        }
        print_data(collect_netw_summary($raw,null));
        foreach($hostsip as $key => $val){
                print_data(collect_netw_summary($raw,$val['hs']));
        }
	print_data(collect_response_time($raw,null));
	foreach($hostsip as $key => $val){
		print_data(collect_response_time($raw,$val['hs']));
	}
}


echo "\n";
?>

