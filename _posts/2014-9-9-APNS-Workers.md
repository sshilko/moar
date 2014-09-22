---
layout: post
title: PHP Workers for sending Apple push notifications
---

#### Introduction

Push em reliable. Practical production solution to deliver notifications fast and efficient covering issues with existing open source solutions.


The raw wire API is described at official Apple [iOS Developer Library](https://developer.apple.com/library/ios/documentation/NetworkingInternet/Conceptual/RemoteNotificationsPG/Chapters/CommunicatingWIthAPS.html).

Assumed basic experience with APNS (Apple push notifications service).

Most popular open-source PHP library at the moment is:

* [ApnsPHP: Apple Push Notification & Feedback Provider](https://code.google.com/p/apns-php/) also available [Github](https://github.com/duccio/ApnsPHP).

Searching for alternatives wont give much results:

* [php-apn](http://libcapn.org/php-apn/) PHP module installed via pecl, wrapping libcapn C library also available [Github](https://github.com/adobkin/php-apn)
* YII [yii-apns-gcm](https://github.com/bryglen/yii-apns-gcm) which is actually using ApnsPHP internally
* YII [yii-EasyAPNs](https://github.com/Mirocow/yii-EasyAPNs)
* Notificato [mac-cain13/notificato](https://github.com/mac-cain13/notificato) by [Mathijs Kadijk](http://mathijskadijk.nl/post/45983847574/notificare-send-pushnotifications-from-php)
* [RMSPushNotificationsBundle](https://github.com/richsage/RMSPushNotificationsBundle) for [Symfony2](http://symfony.com)
* ZendFramework 1.12 & Zend Framework 2
* non-php based solutions (ruby, java, python ...)

#### The issue

Easy approach is to execute push notification dispatch inline with your code, which has a few drawbacks:

* Unpredictable execution time, depending on network conditions just connecting times we saw took up to 15 seconds

* Apple guidelines clearly state that clients should keep few connections open to service

> Keep your connections with APNs open across multiple notifications; don’t repeatedly open and close connections. APNs treats rapid connection and disconnection as a denial-of-service attack. You should leave a connection open unless you know it will be idle for an extended period of time—for example, if you only send notifications to your users once a day it is ok to use a new connection each day.

Correct approach is to have some background daemon/worker, but this creates more complexity we have to handle, as to correctly handling
all the stream connection and correct handling of Apple error codes.

#### [fwrite](http://php.net/manual/en/function.fwrite.php)

`fwrite() returns an int, and this int represents the amount of data really written to the stream`

* Zend_Mobile_Push_Apns is not handling this right [here](https://github.com/zendframework/zf1/blob/master/library/Zend/Mobile/Push/Apns.php#L332) same as ZendService\Apple\Apns\Client\Message [zendframework/zendservice-apple-apns](https://packages.zendframework.com/)
* yii-EasyAPNs is also affected [here](https://github.com/Mirocow/yii-EasyAPNs/blob/master/components/APNS.php#L536) 

* [RMSPush](https://github.com/richsage/RMSPushNotificationsBundle/blob/master/Service/OS/AppleNotification.php#L168) and [Notificato](https://github.com/richsage/RMSPushNotificationsBundle/blob/master/Service/OS/AppleNotification.php#L168) seems to be **not affected**

#### [Shutdown](https://developer.apple.com/library/ios/documentation/NetworkingInternet/Conceptual/RemoteNotificationsPG/Chapters/CommunicatingWIthAPS.html)

* Apple servers as any other servers do go down, and also do this nicely sending the error code 10
this is neither handled by ZF or ApnsPHP, actually the ApnsPHP behaviour to **any error** is to [disconnect](https://github.com/duccio/ApnsPHP/blob/master/ApnsPHP/Push.php#L346) and connect again!

Enough of libraries overview, as none of those except [ApnsPHP](https://github.com/duccio/ApnsPHP/blob/master/sample_server.php) directly provide the daemon/worker needed.

#### Solution

The goal is to

1. offload notifications processing into background
2. keep connection to Apple service open wich give us near-realtime delivery time
3. have a fallback "to inline" solution coz [Murphy's laws](http://www.murphys-laws.com/murphy/murphy-laws.html)
4. solution needs to be simplest, flexible, easy to maintain and easy to deploy.


Unless u have an app compatible with [reachphp](http://reactphp.org/) or any other ([rdlowrey/Amp](https://github.com/rdlowrey/Amp)) non-blocking/multi-threaded/asynchronous php web server your code is executed by web server via SAPI/CGI (i.e. php-fpm or apache), which means php cant keep up the connection to apple server open between the requests, even the code itself can be cached with opCache, the execution environment is fresh for each launch.

One can argue about [pfsockopen](http://php.net/manual/en/function.pfsockopen.php) that `Open persistent Internet or Unix domain socket connection` but from my experience i would NOT recommend any production code to rely on that at all, personally i tried updating ApnsPHP code to use that, but nothing good came out.

The solution is to use an [Messaging queue](http://en.wikipedia.org/wiki/Message_queue), according to 4 goal we need only basic messaging
functionality:

* check if queue is working and there is worker ready to accept a job (for a fallback solution)
* push messages
* get messages
* return (re-queue) messages in case worker goes south

I had choosen [beanstalkd](http://kr.github.io/beanstalkd/) tho i previously worked with [Gearman](http://gearman.org/) which is indeed a good solution, it seend abit too much for a simple task we do, additionnaly

* beanstalkd is easy to deploy (inc. with [puppet](http://puppetlabs.com/)
* small, easy to configure, and production ready.
* [protocol](https://raw.githubusercontent.com/kr/beanstalkd/master/doc/protocol.txt) itself is human readable and easy to use
* library [davidpersson/beanstalk](https://github.com/davidpersson/beanstalk) is small abd ready to go

*Ubuntu* installation is something like:
`apt-get install -y beanstalkd`
And then check trhu `/etc/default/beanstalkd`.

Once we have a messaging queue daemon, next we can start using the queue via [BackQ](https://github.com/sshilko/backq/) library

1. publish (producer) [source](https://github.com/sshilko/backq/blob/master/Publisher/Apnsd.php)
2. subscribe (worker) [source](https://github.com/sshilko/backq/blob/master/Worker/Apnsd.php)

