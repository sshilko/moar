---
layout: post
title: PHP Workers for sending Apple push notifications [BackQ library]
---

#### Introduction to [BackQ library](https://github.com/sshilko/backq/)

Push em reliable. Practical production solution to dispatch notifications fast and efficient with open source solutions. The raw APNS wire API is described at official Apple [iOS Developer Library](https://developer.apple.com/library/ios/documentation/NetworkingInternet/Conceptual/RemoteNotificationsPG/Chapters/CommunicatingWIthAPS.html).

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
2. keep connection to Apple service open which give us near-realtime delivery time
3. have a fallback "to inline" solution [Murphy's laws](http://www.murphys-laws.com/murphy/murphy-laws.html)
4. solution needs to be simplest, flexible, easy to maintain and easy to deploy.


Unless u have an app compatible with [reachphp](http://reactphp.org/) or any other ([rdlowrey/Amp](https://github.com/rdlowrey/Amp)) non-blocking/multi-threaded/asynchronous php web server your code is executed by web server via SAPI/CGI (i.e. php-fpm or apache), which means php cant keep up the connection to apple server open between the requests, even the code itself can be cached with opCache, the execution environment is fresh for each launch.

One can argue about [pfsockopen](http://php.net/manual/en/function.pfsockopen.php) that `Open persistent Internet or Unix domain socket connection` but from my experience i would NOT recommend any production code to rely on that at all, personally i tried updating ApnsPHP code to use that, but nothing good came out.

The solution is to use an [Messaging queue](http://en.wikipedia.org/wiki/Message_queue), according to goals above we require only basic messaging functionality:

* check if queue is working and there is worker ready to accept a job (for a fallback solution)
* push messages
* get messages
* return (re-queue) messages in case worker goes south

I had choosen [beanstalkd](http://kr.github.io/beanstalkd/) over [Gearman](http://gearman.org/) which is also a good solution:

* beanstalkd is easy to deploy (inc. with [puppet](http://puppetlabs.com/)
* small, easy to configure, and production ready.
* [protocol](https://raw.githubusercontent.com/kr/beanstalkd/master/doc/protocol.txt) itself is human readable and easy to use
* library [davidpersson/beanstalk](https://github.com/davidpersson/beanstalk) is small abd ready to go
* is already included as standard tool in [puphpet.com](https://puphpet.com/#additional-tools) puppet script generator

*Ubuntu* installation is something like:
`apt-get install -y beanstalkd`
with configuration  at `/etc/default/beanstalkd`.

Once we have a messaging queue daemon, next we can start using the queue via [BackQ](https://github.com/sshilko/backq/) library.

Library consists of 

1. publisher  (producer) [source](https://github.com/sshilko/backq/tree/master/src/Publisher)
2. subscriber (worker)   [source](https://github.com/sshilko/backq/tree/master/src/Worker)
3. adapter               [source](https://github.com/sshilko/backq/tree/master/src/Adapter)
4. [ApnsPHP adapter](https://github.com/sshilko/backq/blob/master/src/Adapter/ApnsdPush.php), due to [deprecation of SSL by Apple](https://developer.apple.com/news/?id=10222014a) and switching to TLS

Examples and usage described [here](https://github.com/sshilko/backq#usage), existing [APNS worker](https://github.com/sshilko/backq/blob/master/src/Worker/Apnsd.php) uses ApnsPHP library and dispatches messages w/o batching.
Listens for incoming messages on "apnsd" Beanstalkd queue, and [publisher](https://github.com/sshilko/backq/blob/master/src/Publisher/Apnsd.php) pushes messages to the same "apnsd" queue.

Example **worker**

{% highlight php %}
<?php
$log = 'somepath/log.txt';

$ca  = 'somepath/entrust_2048_ca.cer';

$pem = 'somepath/apnscertificate.pem';

$env = \ApnsPHP_Abstract::ENVIRONMENT_SANDBOX;

$worker = new \BackQ\Worker\Apnsd(new \BackQ\Adapter\Beanstalk);

$worker->setLogger(new \BackQ\Logger($log));
$worker->setRootCertificationAuthority($ca);
$worker->setCertificate($pem);
$worker->setEnvironment($env);
//$worker->toggleDebug(true);

$worker->run();

{% endhighlight %}

Example **publisher**

{% highlight php %}
<?php
//array of [ApnsPHP_Message_Custom or ApnsPHP_Message]
$messages  = array();
$publisher = \BackQ\Publisher\Apnsd::getInstance(new \BackQ\Adapter\Beanstalk);

//try connecting to Beanstalkd and ensure there are workers waiting for a job
if ($publisher->start() && $publisher->hasWorkers()) {
    for ($i=0; $i < count($messages); $i++) {
        //allow maximum 3 seconds for worker to give a response on job status, see Beanstalkd protocol for details
        $result = $publisher->publish($messages[$i], array(\BackQ\Adapter\Beanstalk::PARAM_JOBTTR => 3));
        if ($result > 0) {
            //successfull
        }
    }
}

{% endhighlight %}

#### Maintenance

To look over running daemon/process there are [number of tools](http://blog.crocodoc.com/post/48703468992/process-managers-the-good-the-bad-and-the-ugly) available as

* [Upstart](http://upstart.ubuntu.com/)
* [Monit](http://mmonit.com/monit/)
* [Supervisor](http://supervisord.org/) [installation](http://supervisord.org/installing.html)

Monitoring app will look over daemonized scripts and optionally restart them immediattely after exit, this will keep the workers running.  

BackQ worker is implemented in a way it will quit if

1. encounter internal error (socket connection died)
2. apple server decides to reboot (got code 10 from apple)
3. apple server sent UNKNOWN error code 255 or code 1 (processing error)

Configuring supervisor either via [puppet supervisord](https://github.com/puphpet/puppet-supervisord) 

{% highlight bash %}
supervisord::program { 'apnsd':
    command     => "php /path/to/worker/apnsd.php",
    autostart   => true,
    autorestart => true
}
{% endhighlight %}

or manually creating configuration files, on Ubuntu by creating new file `/etc/supervisor.d/program_apnsd.conf` (name does no matter)

{% highlight bash %}
[program:apnsd]
    command=php /path/to/worker/apnsd.php
    autostart=true
    autorestart=true
    user=ubuntu
    stdout_logfile=/var/log/supervisor/program_apnsd.log
    stderr_logfile=/var/log/supervisor/program_apnsd.error
{% endhighlight %}

Starting supervisor will trigger starting "apnsd" worker because `autostart=true`  
 and worker will be restarted immediately upon termination because `autorestart=true`.

Keep in mind that **if supervisord process crashes** all the workers go down, thats edge-case scenario but never know,  
that why u should implement fallback solution and check for `hasWorkers()` with publisher. 
I personnaly just dispatch notifications inline with the same ApnsPHP library as a fallback, since the library is already there.

#### Summary

 By combining

 * Beanstalk queue
 * Supervisor monitoring
 * Existing popular PHP libraries w/o native daemon/queue support

 and writing simple worker/publisher one can reliably with predictible performance/delay serve push notifications.

#### Update Jan 2015
 * As of November 2014 [Apple has removed SSL 3.0 support](https://developer.apple.com/news/?id=10222014a), but [duccio/ApnsPHP](https://github.com/duccio/ApnsPHP) is [not updated](https://github.com/duccio/ApnsPHP/blob/master/ApnsPHP/Push.php#L58) itself
 * Therefore as-is usage of library is broken atm.,
   to fix that i added [Adapter/ApnsdPush](https://github.com/sshilko/backq/blob/master/src/Adapter/ApnsdPush.php) wrapping
   for Push class to use the [TLS](http://en.wikipedia.org/wiki/Transport_Layer_Security) instead of SSL. Please update.

#### Update Feb 2015
 * Performance bottleneck found in [duccio/ApnsPHP](https://github.com/duccio/ApnsPHP).
   The [code](https://github.com/duccio/ApnsPHP/blob/master/ApnsPHP/Push.php#L195) responsible
   for sending stream data relies on [stream_select](http://php.net/stream_select) function which accepts the timeout value set by
   [setSocketSelectTimeout](https://github.com/sshilko/backq/blob/master/src/Worker/Apnsd.php#L114) that is currently hardcoded to 1 second
   without an option to modify that value w/o changing BackQ library code. I'am planning to research for better ways to deal with stream data.

   For now single push can take **up to 1 second** (independent whether its successful or not).

{% highlight php %}
    The tv_sec and tv_usec together form the timeout parameter, tv_sec specifies the number of seconds while tv_usec the
    number of microseconds.
    The timeout is an upper bound on the amount of time that stream_select() will wait before it returns.
    If tv_sec and tv_usec are both set to 0, stream_select() will not wait for data - instead it will return immediately,
    indicating the current status of the streams.

    If tv_sec is NULL stream_select() can block indefinitely, returning only when an event on one
    of the watched streams occurs (or if a signal interrupts the system call).

    Warning
    Using a timeout value of 0 allows you to instantaneously poll the status of the streams, however,
    it is NOT a good idea to use a 0 timeout
    value in a loop as it will cause your script to consume too much CPU time.

    It is much better to specify a timeout value of a few seconds, although if you need to be checking
    and running other code concurrently,
    using a timeout value of at least 200000 microseconds will help reduce the CPU usage of your script.

    Remember that the timeout value is the maximum time that will elapse;
    stream_select() will return as soon as the requested streams are ready for use.
{% endhighlight %}

#### Update 1 Apr 2015
 * Socket select timeout reduced to 0.5 sec doubling the performance
 * PHP 5.2.23 & 5.6.7 completely breaks the funtionality, the code [stucks at fread()](https://github.com/duccio/ApnsPHP/issues/84) (for ini_get("default_socket_timeout") seconds) while trying to get error from service.

#### Update 2 Apr 2015
 * PHP 5.2.23 & 5.6.7 are using stream_socket_client.timeout instead of stream_set_timeout, added option to set custom connect timeouts to be able to make a workarounds

#### Update 3 Jun 2015
 * PHP [5.2.25](http://php.net/ChangeLog-5.php#5.5.25) Fixed bug #69402 (Reading empty SSL stream hangs until timeout).

#### Update 27 Oct 2015
 * Added [symfony/process](http://symfony.com/doc/current/components/process.html) handler to process any kinds of background processes
 * Fixed publisher arguments passing (delay & ttr)
 * Fixed multiple publisher instances (Process & APNS publishers at the same time)
 * Improved zombie processes collector for symfony/process (use `pstree` to look out for zombies); leaving 1 zombie at a time per Process worker is by-design.
 * Reworked [ApnsdPush](https://github.com/sshilko/backq/blob/726c67bf9b8f99a4a8bc28606a6034de974ccbc0/src/Adapter/ApnsdPush.php) Adapter to separate socket layer 
   into separate class (StreamIO & SocketIO); Only StreamIO currently supported, SocketIO support (with SO_KEEPALIVE) is planned;
 * Better error reporting for socket layer issues (fwrite(): SSL: Connection timed out ...), more fixes coming soon

#### Update 04 Dec 2015
   * Added [Message\ApnsPHP](https://github.com/sshilko/backq/commit/33d124d16c5eac041d9ed27f84ce89dd8a80fb26) with 2k payload size instead of 256bytes original (iOS8 upgrade)

#### Update 12 Dec 2015
   * Added setRestartThreshold($n) to quit worker after processing $n amount of jobs
   * Added setIdleTimeout($n) to quit worker if received job after $n seconds of inactivity
   * Fix composer.json and add proper dependencies of apns-php & beanstalkd packages

#### Update 22 Apr 2016
   * Version updated to stable 1.1.2 with many bugfixes & performance improvements
   * The socket write/read layer was rewritten and is bulletproff with custom performance vs reliability setting
   * This may be the last (BinaryProtocol) version, because Apple deployed HTTP2 gateway.

#### Update 11 Jan 2017
   * Version updated to 1.2, fixing some of interface syntax, refactoring queueName logic, doing universal restartThreshold & idleTimeout detection
