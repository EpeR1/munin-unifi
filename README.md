This is a [Munin](http://munin-monitoring.org/) plugin to monitor your [Ubiquiti Unifi](https://www.ubnt.com/products/#unifi) wireless network status.  
It uses SNMPv2 to get network data.

### The original version of this repo is aviable here: [http://git.bmrg.hu/unifi-munin.git](http://git.bmrg.hu/unifi-munin.git/)   





## Usage 
unifi_munin - Munin plugin to monitor UBNT unifi wireless APs

Number of Clients  
![munin](http://git.bmrg.hu/unifi-munin.git/img/munin-ssid.png)  

Network Usage  
![munin](http://git.bmrg.hu/unifi-munin.git/img/munin-netw.png)  




## Installing on Debian

1) Copy the **ubnt_unifi.php** into the **/usr/share/munin/plugins/** folder.  
2) Set the rights:  

     chmod 755 /usr/share/munin/plugins/ubnt_unifi.php  
    
3) Create a symlink to this file:  

     ln -s /usr/share/munin/plugins/ubnt_unifi.php /etc/munin/plugins/ubnt_unifi    
    
4) Edit the **/etc/munin/plugin-conf.d/munin-node** file, and add the following configuration lines.  
5) Test the plugin with the `munin-run ubnt_unifi` command.

## CONFIGURATION

The following environment variables are used:

    [ubnt_unifi]   
      timeout           -   Munin-update timeout for this plugin.  
      env.controller    -   The unifi controller hostname/ip.  
      env.devices       -   A "space" separated list of the hostnames or IP addresses of wireless APs.  
      env.timeout       -   The maximum timeout in milliseconds of SNMP requests. (munin running time!).  
      env.retry         -   Number of retry after failed/time out SNMP requets.  
      env.devnetw       -   The network of the APs. (It is expreimental yet.)  

  
Configuration example for Munin:


     [ubnt_unifi]    
       timeout 240  
       env.controller unifi.company.hu  
       env.devices ap01.wl.company.lan ap02.wl.company.lan ap03.wl.company.lan 10.10.1.6 10.10.1.7 10.10.1.8   
       env.devnetw 10.10.1.10/24  
       env.timeout 70  
       env.retry 1  




### AUTHOR

Copyright (C) 2018 Gergő J. Miklós.



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



