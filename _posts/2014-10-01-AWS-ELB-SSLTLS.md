---
layout: post
title: High-grade Encryption with Amazon AWS Elastic Load Balancers
---

#### AWS ELB Introduction

AWS Provides simple load balancing via [AWS ELB](https://docs.aws.amazon.com/ElasticLoadBalancing/latest/DeveloperGuide/TerminologyandKeyConcepts.html)

Placing a secure server behind ELB is a good thing not only when u need to load-balance requests because:

1. Configurable Instance(s) Health Check (SSL/TCP/HTTPS/HTTP) with alerts via [AWS CloudWatch](http://aws.amazon.com/cloudwatch/)
2. Additionnal CloudWatch Metrics (HTTP 2xx/4xx/5xx ... and so on)
3. Configurable Idle connections timeout with maximum 3600 seconds timeouts (timeout = no HTTP(S)/TCP/SSL traffic at all)
4. ELB also works as a firewall u can configure in just a few clicks allowing and restricting access from outside to instance
5. SSL handler, in case u dont want to handle SSL just upload your certificate and ELB will handle SSL/TLS for you
eliminating SSL library updates/security patches and other complexity that comes with handling SSL
also updating certificates could be done in just few minutes and few clicks with changes coming in effect immediattely
6. SSL Ciphers control, making possible to make a [PCI compliance](https://www.pcicomplianceguide.org/pci-faqs-2/#1) w/o a trouble
or ensuring your secure server will not be accessible by outdated clients (a.k.a Browsers) that only capable of using old SHA-1 crypto.
Making sure clients use [TLSv1.2](http://en.wikipedia.org/wiki/Transport_Layer_Security#TLS_1.2) and [ECDHE](http://www.ecdhe.com/)  protocol for maximum (forward) security available.


Only latest modern browsers support best encryption, setting for example latest encryption setting as

* ECDHE-RSA-AES256-SHA384
* ECDHE-RSA-AES256-SHA

will additionnaly restrict access to old spyware, bots, scanners, crawlers who u dont want to see and specific endpoints,
furthermore crypt settings are per-port, so u can re-route "Old" and "New" clients to different ports without changing
your app configuration simply by pointing both external ports to the same internal port on ELB.


#### Additionnal tools/materials

* SSL Checker [www.ssllabs.com](https://www.ssllabs.com/ssltest/index.html)
* DHE/ECDHE [Wikipedia](http://en.wikipedia.org/wiki/Elliptic_curve_Diffie%E2%80%93Hellman)
* Google [Gradually sunsetting SHA-1](http://googleonlinesecurity.blogspot.de/2014/09/gradually-sunsetting-sha-1.html)
* SHA-1 [known broken since 2005](https://www.schneier.com/blog/archives/2005/02/cryptanalysis_o.html)
* Transport Layer Protection [Cheat Sheet](https://www.owasp.org/index.php/Transport_Layer_Protection_Cheat_Sheet)
* Deprecation of SHA-1 Hashing Algorithm for Microsoft Root Certificate Program [2880823](https://technet.microsoft.com/en-us/library/security/2880823.aspx)