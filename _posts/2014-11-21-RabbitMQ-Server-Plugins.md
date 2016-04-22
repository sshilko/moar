---
layout: post
title: Building RabbitMQ with plugins on AWS EC2
---

#### Realtime Messaging Broker & MQTT

Guide on building latest RabbitMQ 3.4.1 on Ubuntu Server 14.04.

* [RabbitMQ](http://www.rabbitmq.com/getstarted.html) [Message Broker](http://en.wikipedia.org/wiki/Message_broker)
* Custom plugin build
* EC2 minimal instance setup for RabbitMQ

#### Requirements

* [AWS](http://aws.amazon.com/) cloud account and basic experience launching [EC2](http://aws.amazon.com/ec2/) instances
* basics of make, [Ubuntu Linux](http://www.ubuntu.com/server), ssh, [hg](http://mercurial.selenic.com/).

#### Installing and configuring RabbitMQ

The first step is to find appropriate 14.04 [Ubuntu Server Releases](http://uec-images.ubuntu.com/releases/14.04.1/release/) which at
the moment is for US EAST (N.Virginia) datacenter:

* ubuntu/images/ebs-ssd/ubuntu-trusty-14.04-amd64-server-20140927 - **ami-98aa1cf0** (64 bit)

A. Launch the instance and connect via SSH replacing "ip" with instance ip address, default username is "ubuntu"

{% highlight bash %}
ssh -i key.pem ubuntu@ip
{% endhighlight %}

u should see something like

{% highlight bash %}
Welcome to Ubuntu 14.04.1 LTS (GNU/Linux 3.13.0-36-generic x86_64)
{% endhighlight %}

B. Set instance hostname because RabbitMQ stores its state in [Mnesia](http://www.erlang.org/doc/man/mnesia.html) database locally in files and database name is connected to hostname.

{% highlight bash %}
sudo su
hostname rmq1.myhost.com
echo 'rmq1.myhost.com' > /etc/hostname
echo '127.0.0.1 rmq1.myhost.com' >> /etc/hosts
apt-get update
reboot
{% endhighlight %}


C. Default RabbitMQ for Ubuntu Server 12.04 and 14.04 is [rabbitmq-server-3.2.4](https://launchpad.net/ubuntu/trusty/amd64/rabbitmq-server),
but its outdated as [latest stable](http://www.rabbitmq.com/news.html#2014-10-29T14:26:55+0000) is [rabbitmq-server-3.4.1](http://www.rabbitmq.com/release-notes/README-3.4.1.txt).

To install latest version use the official repository and generally follow build-server [instructions](http://www.rabbitmq.com/build-server.html).

After instance reboots ssh to it as root again.

{% highlight bash %}
sudo su
echo 'deb http://www.rabbitmq.com/debian/ testing main' >> /etc/apt/sources.list
wget http://www.rabbitmq.com/rabbitmq-signing-key-public.asc
apt-key add rabbitmq-signing-key-public.asc
apt-get update
apt-get install rabbitmq-server -y

# should see something like
#...
#... http://www.rabbitmq.com/debian/ testing/main rabbitmq-server all 3.4.1-1 [4,059 kB]
#...
# Additionnaly for plugin development and compiling server from sources install dependencies

apt-get install mercurial -y
apt-get install make -y
apt-get install xsltproc -y
apt-get install python-setuptools -y ; easy_install simplejson;
{% endhighlight %}

D. Additionnal requirements not clearly mentioned by official documentation are [erlang-dev](https://launchpad.net/ubuntu/trusty/+package/erlang-dev), [erlang-src](https://launchpad.net/ubuntu/trusty/+package/erlang-src), [zip](https://launchpad.net/ubuntu/trusty/+package/zip).

{% highlight bash %}
#rlc -o ebin -I include -Wall -v +debug_info   -DINSTR_MOD=gm -pa ebin src/rabbit_ssl.erl
#src/rabbit_ssl.erl:21: can't find include lib "public_key/include/public_key.hrl"
#src/rabbit_ssl.erl:98: undefined macro 'id-at-commonName'
#src/rabbit_ssl.erl:146: undefined macro 'id-at-surname'
#src/rabbit_ssl.erl:50: record 'OTPCertificate' undefined
#src/rabbit_ssl.erl:53: variable 'Issuer' is unbound
#src/rabbit_ssl.erl:58: record 'OTPCertificate' undefined
#src/rabbit_ssl.erl:61: variable 'Subject' is unbound
#src/rabbit_ssl.erl:66: record 'OTPCertificate' undefined
#src/rabbit_ssl.erl:69: variable 'Subject' is unbound
#src/rabbit_ssl.erl:74: record 'OTPCertificate' undefined
#src/rabbit_ssl.erl:77: variable 'Start' is unbound
#src/rabbit_ssl.erl:78: variable 'End' is unbound
#src/rabbit_ssl.erl:84: function peer_cert_auth_name/2 undefined
#src/rabbit_ssl.erl:123: variable 'V' is unbound
#src/rabbit_ssl.erl:123: record 'AttributeTypeAndValue' undefined
#src/rabbit_ssl.erl:125: variable 'T' is unbound
#src/rabbit_ssl.erl:140: function format_rdn/1 undefined
#src/rabbit_ssl.erl:105: Warning: function auth_config_sane/0 is unused
#src/rabbit_ssl.erl:174: Warning: function escape_rdn_value/1 is unused
#src/rabbit_ssl.erl:177: Warning: function escape_rdn_value/2 is unused

apt-get install erlang-dev erlang-src -y

#/bin/sh: 1: zip: not found

apt-get install zip -y
{% endhighlight %}

E. Relogin as normal user 'ubuntu' (quit sudo) and clone the [RabbitMQ public umbrella mercurial repository](http://hg.rabbitmq.com/rabbitmq-public-umbrella) and switch to stable branch

{% highlight bash %}
hg clone http://hg.rabbitmq.com/rabbitmq-public-umbrella umbrella
cd umbrella
hg co rabbitmq_v3_4_1
make co
make BRANCH=rabbitmq_v3_4_1 up_c
{% endhighlight %}

At this stage u should have 3.4.1 branch checked out with all dependencies and all build requirements installed.

#### Modifying plugin

Example plugin we are going to modify is [MQTT Adapter](http://www.rabbitmq.com/blog/2012/09/12/mqtt-adapter/).
A powerful adapter enabling [MQTT Protocol](http://mqtt.org/) connectivity wich powers industry leading services already, i.e.
in [realtime messengers](https://www.facebook.com/notes/facebook-engineering/building-facebook-messenger/10150259350998920) and telemetry apps ([internet of things](http://www.hivemq.com/how-to-get-started-with-mqtt/)).

An example modifications to MQTT plugin are:

* disabling the [QoS1 publish & consume](http://docs.oasis-open.org/mqtt/mqtt/v3.1.1/os/mqtt-v3.1.1-os.html#_Toc398718101) (at least once delivery) to prevent any state handling by broker (stateless broker) and thus increasing performance
* disabling the [Last Will and Testament (LWT)](http://docs.oasis-open.org/mqtt/mqtt/v3.1.1/os/mqtt-v3.1.1-os.html#_Toc398718031) also to prevent creating any server state or business logic

> Last Will and Testament (LWT):
Clients can provide a LWT message during connection that will only be published if the client disconnects unexpectedly, e.g. due to a network failure.

Lets make small change to MQTT plugin, disabling the LWT (below are [Erlang](http://www.erlang.org/) sources of MQTT plugin) by just commenting one line and compiling


{% highlight bash %}
cd rabbitmq-mqtt
vim src/rabbit_mqtt_processor.erl

--- a/src/rabbit_mqtt_processor.erl    
+++ b/src/rabbit_mqtt_processor.erl    
@@ -91,7 +91,7 @@
                                 rabbit_mqtt_reader:start_keepalive(self(), Keepalive),
                                 {?CONNACK_ACCEPT,
                                  maybe_clean_sess(
-                                   PState #proc_state{ will_msg   = make_will_msg(Var),
+                                   PState #proc_state{ %% will_msg   = make_will_msg(Var),
                                                        clean_sess = CleanSess,
                                                        channels   = {Ch, undefined},
                                                        connection = Conn,


{% endhighlight bash %}


{% highlight bash %}
make
{% endhighlight bash %}

after editing, make a symlink to rabbitmq plugins folder in our home folder

{% highlight bash %}
cd ~; ln -s /usr/lib/rabbitmq/lib/rabbitmq_server-3.4.1/plugins/ .
#so plugins will point to rabbitmq
plugins -> /usr/lib/rabbitmq/lib/rabbitmq_server-3.4.1/plugins/
{% endhighlight bash %}

Now as we compiled the plugin, replace the original plugin with our own:

{% highlight bash %}
ubuntu@rmq1:~/umbrella/rabbitmq-mqtt$ ls -l dist/
total 876
-rw-rw-r-- 1 ubuntu ubuntu 260608 Nov 21 10:27 amqp_client-0.0.0.ez
-rw-rw-r-- 1 ubuntu ubuntu 562928 Nov 21 10:27 rabbit_common-0.0.0.ez
-rw-rw-r-- 1 ubuntu ubuntu  65559 Nov 21 10:27 rabbitmq_mqtt-0.0.0.ez

# our modified compiled plugin lies in dist folder

#stop the server before making changes to plugin files
sudo su
/etc/init.d/rabbitmq-server stop

#overwrite the original plugin with our compiled version
root@rmq1:/home/ubuntu# cp umbrella/rabbitmq-mqtt/dist/rabbitmq_mqtt-0.0.0.ez plugins/rabbitmq_mqtt-3.4.1.ez
{% endhighlight bash %}

#### RabbitMQ plugins and configuration for low-end EC2 instance

Now lets properly configure rabbitmq with config that would run on low performance EC2 instance:

* configure 100Mb lower limit on free disk space
* allow server to use 85% of available system memory
* disable access logging and optimize network settings

create a file at /etc/rabbitmq/rabbitmq.config with following contents

{% highlight bash %}
sudo su
touch /etc/rabbitmq/rabbitmq.config

[
 %% see https://www.rabbitmq.com/configure.html
 {rabbit, [ {disk_free_limit, 100000000},
            {vm_memory_high_watermark, 0.85},
            {heartbeat, 30},
            {collect_statistics, 'none'},
            {log_levels, [{connection, none}]},
            {tcp_listeners, [5672]},
            {tcp_listen_options, [binary, {packet,    raw},
                                          %{reuseaddr, true},
                                          {backlog,   128},
                                          {nodelay,   true},
                                          %% http://erlang.org/doc/man/inet.html#setopts-2
                                          {delay_send, false},
                                          {keepalive,  false},
                                          {recbuf,     1024},
                                          {sndbuf,     1024}
                                 ]},
                                
            {auth_backends, [rabbit_auth_backend_internal]}

            %% {tcp_listeners, []}, %%no tcp, must declare empty value otherwise default 5672 will be running
            %% {ssl_listeners, [5672]},
            %% {ssl_options, [{cacertfile, "/etc/rabbitmq/ca.crt"},
            %%                {certfile,   "/etc/rabbitmq/server.crt"},
            %%                {keyfile,    "/etc/rabbitmq/server.key"},
            %%                {verify,     verify_none},
            %% %%verify_none = no exchange takes place from the client to the server
            %%                {fail_if_no_peer_cert,false}
            %% %%prepared to accept clients which don't have a certificate to send us
            %% ]}
 ] },

  {rabbitmq_management, [{listener, [{port, 15672}

            %%                      {ssl, false},
            %%                      {ssl_opts,    [{cacertfile, "/etc/rabbitmq/ca.crt"},
            %%                                     {certfile,   "/etc/rabbitmq/server.crt"},
            %%                                     {keyfile,    "/etc/rabbitmq/server.key"},
            %%                                     {verify,     verify_none},
            %% %%verify_none = no exchange takes place from the client to the server
            %%                                     {fail_if_no_peer_cert,false}
            %% %%prepared to accept clients which don't have a certificate to send us
            %%                                    ]}                             
                                   ]}
 ] },

 {rabbitmq_mqtt, [{allow_anonymous,  false},
                  {vhost,            <<"/vhost1">>},
                  {exchange,         <<"exchange1">>},
                  {subscription_ttl, 90000},
                  {prefetch,         0},
                  {ssl_listeners,    []},
                  {tcp_listeners,    [1883]},
                  {tcp_listen_options, [binary,
                                       {packet,    raw},
                                       {reuseaddr, true},
                                       {backlog,   128},
                                       {nodelay,   true}]}
 ] }

].
{% endhighlight bash %}

Enable MQTT and management plugins then restart the server.

{% highlight bash %}

echo '[rabbitmq_management,rabbitmq_mqtt].' >  /etc/rabbitmq/enabled_plugins;
/etc/init.d/rabbitmq-server start

# check the logs for successful start report
root@rmq1:/home/ubuntu# tail /var/log/rabbitmq/rabbit\@rmq1.log

=INFO REPORT====
Server startup complete; 7 plugins started.
 * rabbitmq_management
 * rabbitmq_web_dispatch
 * webmachine
 * mochiweb
 * rabbitmq_management_agent
 * rabbitmq_mqtt
 * amqp_client

# ...
{% endhighlight bash %}

Our modified MQTT plugin successfuly started together with the [Management Plugin](https://www.rabbitmq.com/management.html) started and should be available at local port 15672.

> otherwise check the /var/log/rabbitmq/startup_err for startup error logs

#### RabbitMQ vhost, exchange, users setup

We declared the MQTT plugin to be bound to to "/vhost1" and exchange="exchange1" but never created them, so MQTT connections will not work.
To define those entities we need a tool [rabbitmqadmin](https://www.rabbitmq.com/management-cli.html) from the management plugin.

{% highlight bash %}

#first get the rabbitmqadmin

cd ~
wget http://localhost:15672/cli/rabbitmqadmin
chmod +x rabbitmqadmin

./rabbitmqadmin declare exchange name=exchange1 type=topic durable=true
#exchange declared

sudo rabbitmqctl add_vhost vhost1
#Creating vhost "vhost1" ...
{% endhighlight bash %}

Additionnaly put some basic security measures redefining the default "guest" user:

{% highlight bash %}
sudo rabbitmqctl add_user user1 pass1
#Creating user "user1" ...

sudo rabbitmqctl set_permissions -p vhost1 user1 ".*" ".*" ".*"
#Setting permissions for user "user1" in vhost "vhost1" ...

#Optionaly make user1 an administrator that can access the Management UI
#by setting tags for user "user1" to [administrator] ...
sudo rabbitmqctl set_user_tags user1 administrator

#Delete the default user
sudo rabbitmqctl delete_user guest

#Deleting user "guest" ...
{% endhighlight bash %}


#### Finally
Launch the server with modified MQTT plugin and optimized settings.
{% highlight bash %}
/etc/init.d/rabbitmq-server restart
{% endhighlight bash %}
