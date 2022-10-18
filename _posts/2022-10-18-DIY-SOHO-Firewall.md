---
layout: post
title: Filtering home internet with RaspberryPi, Squid, Pi-hole
---

### Introduction 

Modern internet is full of tracking, identity theft, spam, offensive content, fake profiles and scam websites trying to steal your payment information.

For many years i had developed a family-friendly filter solution for my home wireless LAN.

This document describes that solution briefly.

### Problems to solve

Facebook (Meta), Whatsapp, Discord, Instagram, VK, Youtube, all offer some
level of content moderation on their platforms, but clicking a link only takes seconds, while dealing with consequances can take weekends.

Instead, the pro-active or preventive solution is needed at home.

Brief list of problems modern internet throws at a generic user

* Ads, this is waste of focus and distraction
* Phishing or scam websites
* No default content filters on websites like Youtube
* Virus or malware infection spreading across LAN
* Unlimited unmonitored usage
* Torrents and other illegal downloads
* Performance bottlenecks by ISP

### Goal we want to reach

We want safe internet for our dear ones, that works transparently.

### How we did it

The main pieces are the hardware & software.

- Raspberry Pi 3
- TP-LINK WIFI AP
- FritzBox 7490 DSL Router
- PiHole, Squid

##### Hardware

We need 802.11n 2.4Ghz or better (WIFI4) AccesPoint
- 2.4Ghz has better coverage than 5Ghz in residential areas
- 2.4Ghz is compatible with all wireless devices
- Good performance and cheap, at least few MIMO beams
- Low energy consumption, small physical size, recent cpu model

Few options:
- Mesh of [TP-LINK Omada EAP245](https://www.tp-link.com/us/business-networking/omada-sdn-access-point/eap245/) for large areas
- [TP-Link AX50](https://www.tp-link.com/de/home-networking/wifi-router/archer-ax50/) or better for single appartment


As a server we need low-power/arm device, i will be using Debian OS,
so an obvious choise is [Raspberry Pi 3](https://www.raspberrypi.com/products/raspberry-pi-3-model-b/)

#### Software

- [Debian DietPi](https://dietpi.com/stats.html)
  - built-in PiHole & Squid installer
  - stable and can survive without maintenance
- [PiHole](https://pi-hole.net/) DNS server
  - local caching and filtering DNS resolver
  - easy web UI for administration
  - great community support and pre-compiled stop-lists
- [Squid](http://www.squid-cache.org/) 
  - transparent proxy
  - just works
- free DNS by [cleanbrowsing.org](cleanbrowsing.org) for [managed DNS](https://cleanbrowsing.org/filters/) (including DoH) filters
  - family filter
  - adult filter
  - securit filter

PiHole is not yet able to perform [DnsOverHttp requests](https://docs.pi-hole.net/guides/dns/cloudflared/), for that
additionnal proxy need to be installed like cloudflared.


#### DietPi

![Antivirus](/images/diy-firewall/dietpi-1.png)
![Antivirus](/images/diy-firewall/dietpi-2.png)

#### PiHole

![PiHole](/images/diy-firewall/pihole.png)

#### TP-Link AX50

- Small form-factor
- Enforce AES and 20Mhz channel width for stable connection
- MIMO, 4 antennas is at least 1 or 2 beams
- Recent Intel CPU chip

![wifi](/images/diy-firewall/ax50.jpg)


![wifi](/images/diy-firewall/wifi-controls.png)


#### TP-Link HomeCare - Basic virus protection

![Antivirus](/images/diy-firewall/antivirus.png)

#### TP-Link HomeCare - QOS controls

![Filter](/images/diy-firewall/qos_category.png)

![Time](/images/diy-firewall/qos_time.png)


#### TP-Link - DHCP DNS control & MAC whitelisting

![DHCP](/images/diy-firewall/dhcp-controls.png)


The rest of the features are not so relevant and up-to date with current medium tier routers.
- IPv6
- VPN Client
- Alexa integration
- USB3 sharing
- DMZ, UPNP, PortTriggering
- IP/MAC binding
- Isolated Guest WIFI network
- IPTV, Static Routing, manual WAN IP, Dynamic DNS

#### FritzBox

Great DSL router with built-in filtering functionality.
- Built-in german filters of [Federal Department for Media Harmful to Young Persons](https://www.bzkj.de)
- Can block any traffic by source/destination ports/ranges

![Fritzbox](/images/diy-firewall/fritzbox-1.png)
![Fritzbox](/images/diy-firewall/fritzbox-2.png)

### Solution

- `192.168.178.19`  WIFI-AP, TP-LINK AX50 (LAN IP 192.168.0.1)
- `192.168.178.1  ` is internet-router and gateway
- `192.168.178.100` DebianPi, Pi-Hole, Squid, RaspberryPi

What we want to achieve is to enforce usage of our PiHole local DNS server.
1. Login to InternetRouter/FritzBox, and create filtering lists that will block all UDP/TCP traffic where source is WIFI-AP.

This will block all HTTP, HTTPS, QUIC, and DNS traffic to internet from WIFI AccessPoint.

Assing filtering-list to WIFI-AP device, bind mac&ip-address of device.

This way all clients connected to WIFI-AP have no access to internet.

2. Install DietPi on sd/mmc/usb storage
- plug it into Raspberry Pi
- power RaspberryPi from USB port of WIFI-AP

3. Install Squid `apt-get install squid` on DietPi
SSH into DietPi instance and configure Squid to use the PiHole DNS resolver, and to listen 
on port 3128 for HTTP and HTTPS traffic.

`/etc/squid/squid.conf`
```
dns_v4_first on
dns_nameservers 127.0.0.1
cache_mem 512 MB
http_port 3128
```

4. Configure Web Proxy Autodiscovery Protocol (WPAD)

create a text-file, replacing `192.168.178.100` with static IP address of Squid (same as PiHole in our case).
```
function FindProxyForURL(url, host) {
    if (isInNet(host, "192.168.0.0", "255.255.0.0")) {
        return "DIRECT";
    }

    if (host == "127.0.0.1" || isPlainHostName(host)) {
        return "DIRECT";
    }
    return "PROXY 192.168.178.100:3128";
}
```
SSH into PiHole and create file(s) at with above content (or symlink)
```
/var/www/wpad
/var/www/wpad.da
/var/www/wpad.dat
/var/www/proxy.pac
```

Now iOS, Android, MacOS and Windows clients will be able to auto-detect proxy settings.

5. Login into FritzBox and disable WPAD filtering.

![Fritzbox-Wpad](/images/diy-firewall/fritzbox-wpad.png)

6. Log-in to DietPi Web UI http://192.168.178.100/admin/

Set Upstream DNS to `cleanbrowsing.org` IP's
![Pihole-DNS](/images/diy-firewall/pihole-upstreamdns.png)

Enable `Never forward reverse lookups for private IP ranges`

Go to `List of local DNS domains` and enter following to enable WPAD discovery

```
wpad	            192.168.178.100	
wpad.box	        192.168.178.100	
wpad.com	        192.168.178.100	
wpad.domain.local	192.168.178.100	
wpad.fritz.box	    192.168.178.100	
wpad.fritzbox.com	192.168.178.100	
wpad.local	        192.168.178.100	
wpad.localdomain	192.168.178.100
```

Go to `Group Management > Adlists` and add few stop-lists like
one from https://github.com/StevenBlack/hosts then update Gravity.

7. Login to WIFI-AP and set DHCP settings DNS to the PiHole IP 192.168.178.100

7. If WPAD auto discovery does not work, use manual proxy settings when connecting to WIFI as `http://192.168.178.100:3128`

#### Summary

Lets review the initial goals

Filter
> Ads, this is waste of focus and distraction

Deny access to 
> Phishing or scam websites

Set Youtube & Google safe-content filters ON
> No default content filters on websites like Youtube

Give some built-in virus protection
> Virus or malware infection spreading across LAN

Allow to limit internet access by day/time
> Unlimited unmonitored usage

Block TCP/UDP traffic except HTTP(S)
> Torrents and other illegal downloads

Optimize DNS fetching and content cache with Squid
> Performance bottlenecks by ISP


*This setup will block ALL traffic except HTTP and HTTPS*

To lower restrictions: unblock required ports at the DSL/Internet router.

Solution also **breaks openvpn/wireguard VPN**, because VPN is not able to work via Squid HTTP proxy port (not an HTTP traffic).

In future more control could be added via PFsense or open-source DPI applications.

 ![Filtering-schema](/images/diy-firewall/schema.png)

##### Bonus ZX-Spectrum 80s millenials hype

* [Unreal Speccy Portable](https://bitbucket.org/djdron/unrealspeccyp/wiki/Home)
* [Unreal Speccy Portable - Twitter](https://twitter.com/UnrealSpeccyP)
* [Unreal Speccy Portable - Chrome extension](https://chrome.google.com/webstore/detail/unreal-speccy-portable-zx/jfkpejkiedieehgdecgcjbmcbpihimmb?hl=en)
* [Portable ZX-Spectrum emulator supports Z80](https://www.openhub.net/p/unrealspeccyp)
* [ZX Spectrum DEMOSCENE archive](http://bbb.retroscene.org/)
* [Full Tape Crack Pack](http://spectrum4ever.org/fulltape.php?go=releases&id=4&by=cracker)
* [Speccy Emu](http://alonecoder.narod.ru/zx/)
* [ZX Review 1996 - 1997](http://zxpress.ru/issue.php?id=277)
* [ZX News 1996 - 2000](http://zxpress.ru/issue.php?id=269#01)

![Target-Renegade](/images/diy-firewall/target-renegade.png)


#### Update Oct 2022
* Initial release
* *CRACKED BY BILL GILBERT ©*
