### This is a [Munin](http://munin-monitoring.org/) plugin to monitor your [Ubiquiti Unifi](https://www.ubnt.com/products/#unifi) wireless AP status.  
* It queries the APs via SNMPv2 and converts result for munin.  
* Requires:  
    * Enabled SNMP on Access Points.  
    * Network access from munin-node server to AP's network.  
    * PHP 7.0 or above
    * PHP SNMP module
    * PHP JSON module
    * Debian(9+): `sudo apt-get install php php-cgi php-snmp php-json`
* It can use php child-processes to get responses faster.  
* If snmp oids are different on your product, you can use  
 `snmpwalk -v2c -c public ap01.network.lan 'iso.3.6.1.4.1.41112'` command to clarify them.  
* Official UBNT Unifi SNMP MIBs are available here: [Forum](https://community.ui.com/questions/MIBs-/a0365341-b14f-441b-9484-fd4be414d281) 
* Tested with: AP-AC-PRO (up to now).


## Usage 
unifi_munin - Munin plugin to monitor UBNT unifi wireless APs

Number of Clients  
![munin](http://git.bmrg.hu/images/munin-unifi.git/munin-ssid.png)  

Network Usage  
![munin](http://git.bmrg.hu/images/munin-unifi.git/munin-netw.png)  

Ap-response time  
![munin](http://git.bmrg.hu/images/munin-unifi.git/munin-ping.png)


## Installing on Debian

1. Copy the **ubnt_unifi.php** into the **/usr/share/munin/plugins/** folder.  
   
2. Set the rights:  
`chmod 755 /usr/share/munin/plugins/ubnt_unifi.php`  

3. Create a symlink to this file:  
`ln -s /usr/share/munin/plugins/ubnt_unifi.php /etc/munin/plugins/ubnt_unifi`  

4. Edit the **/etc/munin/munin.conf** and **/etc/munin/plugin-conf.d/munin-node** files, add the following configuration lines.  

5. Restart the munin, and munin-node with `/etc/init.d/munin restart` and `/etc/init.d/munin-node restart` commands.  

6. Test the plugin with the `munin-run ubnt_unifi` command.  

7. Check for munin configuration with: `munin-run ubnt_unifi config` command.  

8. Debug information are available under `munin-run ubnt_unifi debug` command.  
  


## CONFIGURATION

Edit the **/etc/munin/munin.conf** with the following options:  

    [unifi.company.com]   #Unifi Controller hostname
      address 127.0.0.1   #This plugin uses a wirtual munin node on localhost,
      use_node_name no    #but don't need to use the node name.
      timeout 240         #Timeout, while this plugin can be runned by munin. (whole running time).


Edit the **/etc/munin/plugin-conf.d/munin-node**, and use the following configurations:  

    [ubnt_unifi]   
      timeout           -   Munin-update timeout for this plugin.  
      env.controller    -   The unifi controller hostname/ip.  
      env.devices       -   A "space" separated list of the hostnames or IP addresses of wireless APs.  
      env.timeout       -   The maximum timeout in milliseconds for SNMP requests. (must enough to get all data from one AP!).  
      env.retry         -   Number of retry after failed/time out SNMP requets.  
      env.maxproc       -   Maximum nuber of child processes (for SNMP get)
      env.devnetw       -   The network of the APs. (COMMENT IT OUT, IF NOT USED !!!)  
      env.resolvdup     -   Clarify if Ap is duplicated (Listed via hostname at "devices", and also is in "devnet" network/mask)  

  
For example:

     [ubnt_unifi]    
       timeout 240  
       env.controller unifi.company.com
       env.devices ap01.wl.company.lan ap02.wl.company.lan ap03.wl.company.lan 10.10.1.6 10.10.1.7 10.10.1.8   
      #env.devnetw 10.10.1.10/24  
       env.timeout 850  
       env.retry 3  
       env.maxproc 32  
       env.resolvdup 1  


### DEBUG Checklist  

* Munin output with: `munin-run ubnt_unifi` command.  
* Munin configuration with: `munin-run ubnt_unifi config` command.  
* Debug information with: `munin-run ubnt_unifi debug` command.  
* Is there a direct connection (Routing/SNMP_port: 161) between munin-server and Access Points?  
* Is "php-json" and "php-snmp" installed?
* Is SNMP enabled in Unifi configuration, or on Access Points?
* Try ping APs from munin server.  


---

### AUTHOR

Copyright (C) 2018-2020 Gergő J. Miklós.



### LICENSE

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; version 2 dated June,
1991.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.



