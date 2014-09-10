---
layout: post
title: PHP Workers for sending Apple push notifications
---

#### Introduction

Push em reliable. Practical production solution to deliver notifications fast and efficient covering issues with existing open source solutions.


The raw wire API is described at official Apple [iOS Developer Library](https://developer.apple.com/library/ios/documentation/NetworkingInternet/Conceptual/RemoteNotificationsPG/Chapters/CommunicatingWIthAPS.html).

We'r not going to touch basic setup and requirement to interact with the service and assume basic experience with APNS (Apple push notifications service).

Atm. most popular open-source PHP library is:

* [ApnsPHP: Apple Push Notification & Feedback Provider](https://code.google.com/p/apns-php/) also available [Github](https://github.com/duccio/ApnsPHP).

Searching for alternatives wont give much results

* [php-apn](http://libcapn.org/php-apn/) PHP module installed via pecl, wrapping libcapn C library also available [Github](https://github.com/adobkin/php-apn)
* YII / ZendFramework / Symfony / ... modules

#### The issue
