---
layout: post
title: Newrelic APM - observability for early-stage PHP projects
---

Overview of using [NewRelic](https://docs.newrelic.com/docs/apm/agents/php-agent/getting-started/introduction-new-relic-php/)
in PHP projects from 1RPM to ±80000 RPM.

## Early-stage project issues

As a startup most companies usually focus on releasing MVP (minimum viable product) and enter the early-adopters market, and rarely invest resources  into topics like

- Observability, Tracing, EventSourcing, CQRS
- Code quality tools
- Long-term vision, flexible architecture
- Unit/Testing, functional testing, integrational testing
- Performance optimizatios
- Security audit
- Internal audit & logging, compliance frameworks
- Testing, staging environment, dedicated QA engineering team

at best projects starts with some framework, coffee, friday pizza and few team-mates.

## Measurement toolbox

Part of scientifical approach to solve a problem is [measurements](https://en.wikipedia.org/wiki/Metrology).

Without measurements, one could only **assume** what happens when changes are deployed to remote servers, based on previous "knowledge" and expertise.

In reality code will not work the same way on a 2-4 code x86 laptop and on Xeon/Opteron cloud KVM/xVM virtualized machine.

A good example of this is effort by Debian OS team named [ReproducibleBuilds](https://wiki.debian.org/ReproducibleBuilds), an attempt to reach binary-identical application builds.

### Docker
Docker attempts solve the build and **delivery** problem of
shipping the application with some API contracts/cgroups (disk, memory, cpu).

Docker is still reuses the kernel of the host [differences between Docker and VirtualMachine](https://geekflare.com/docker-vs-virtual-machine/)), and that "cloud runtime" would **not be the same as local** environment.

### Cloud runtime
The application runtime is virtual, see [AWS Nitro System](https://aws.amazon.com/ec2/nitro/)

- virtual networks, [SDN](https://en.wikipedia.org/wiki/Software-defined_networking), [AWS VPC](https://aws.amazon.com/vpc/), [AWS Api Gateway](https://aws.amazon.com/api-gateway/)
- virtual disk, see [AWS EBS Volumes](https://docs.aws.amazon.com/AWSEC2/latest/UserGuide/ebs-volumes.html)
- virtual CPU's, see [AWS EC2 Instance Types](https://aws.amazon.com/ec2/instance-types/), [AWS Lambda](https://aws.amazon.com/blogs/aws/new-for-aws-lambda-container-image-support/)
- virtual architecture (emulation) [HVM, PV](https://docs.aws.amazon.com/AWSEC2/latest/UserGuide/virtualization_types.html), cpu instructions, cpu cache, [dedicated hosts](https://aws.amazon.com/ec2/dedicated-hosts/) ...
- difference between executing against local and managed services
  - Redis vs [AWS Elasticache](https://aws.amazon.com/free/database)
  - [local os filesystem](https://en.wikipedia.org/wiki/File_system) vs [AWS S3](https://aws.amazon.com/s3/) vs [Min.io](https://min.io/product/s3-compatibility)
  - [Oracle MySQL](https://www.mysql.com) vs [AWS RDS MySQL](https://aws.amazon.com/rds/) vs AWS DynamoDB vs [LocalDynamoDB](https://hub.docker.com/r/amazon/dynamodb-local/)

## What is NewRelic

[![NewRelic One](/images/newrelic/nrone.webp)](/images/newrelic/nrone.webp)

## Why use NewRelic

From early 2012 NewRelic was trying to address this issues, and provided
what is currently named "Observability"
- Logs
- Metrics
- Tracing

### This is what NewRelic APM provides.

- Sidecar container for collecting metrics with 0-performance overhead
- PHP excension with kernel-level access to resources
- Cloud console for monitoring, alerting, aggregation (i.e. Graphana, PagerDuty, Kibana) all together
- Well documented and production tested (crash free) commercial solution
- Logs collector
- Performance metrics collector
- Traces collector

## Monitor your app's Apdex (end-user satisfaction)

NewRelic defines [AppDex](https://docs.newrelic.com/docs/apm/new-relic-apm/apdex/apdex-measure-user-satisfaction/) user satisfaction metric as health, and as developer you can customize this value
```
appdex OK = response time < 1s
appdex WARNING = response time between 1s and 2s
appdex ERROR = response time >2s
```

[![NewRelic AppDex end user satisfaction](/images/newrelic/appdex.png)](/images/newrelic/appdex.png)


## NewRelic overview

[High-level summary]((https://docs.newrelic.com/docs/infrastructure/infrastructure-ui-pages/infrastructure-hosts-page/)) & low-level details of your app in production.

- Response time
- Throughput
- Error rate
- CPU usage
- Memory
- Summary overview
  - System: Overview of your hosts' performance
  - Network: Bandwidth and error data about your network interfaces
  - Processes: Data about CPU percentage, I/O bytes, and memory usage for individual or groups of processes
  - Storage: Resources' capacity and efficiency, including your devices' overall utilization, disk usage, or I/O operations

### Automatic Service Map without mesh
 [![NewRelic Service Map](/images/newrelic/service-map.png)](/images/newrelic/service-map.png)

### Infrastructure monitoring
 [![NewRelic Infra](/images/newrelic/nr-infra.png)](/images/newrelic/nr-infra.png)

### Browser monitoring (JS)
 [![NewRelic Browser](/images/newrelic/nr-browser.png)](/images/newrelic/nr-browser.png)

### LowLevel transactions overview
 [![NewRelic Transaction](/images/newrelic/newrelic-transaction-trace.png)](/images/newrelic/newrelic-transaction-trace.png)

### LowLevel Database, SQL & NoSQL overview
[![NewRelic DB trace](/images/newrelic/newrelic-transaction-trace-db.png)](/images/newrelic/newrelic-transaction-trace-db.png)

### Error and Exception traces
![NewRelic Error trace](/images/newrelic/newrelic-transaction-trace-error.png)

### Key transactions vs All transactions traces
[![NewRelic Key trace](/images/newrelic/newrelic-transaction-trace-key.png)](/images/newrelic/newrelic-transaction-trace-key.png)
  
## How to install

PHP agent consists of two basic components:

A PHP extension, which collects data from your application
A local proxy daemon, which transmits the data to New Relic

* bonus Infrastructure agent (free!)

It comes in different mediums

- tar zip file with agent, same with infrastructure agent
- monitoring agent docker container
- infrastructure agent docker container
- [php-fpm/apache/cli](https://docs.newrelic.com/docs/apm/agents/php-agent/configuration/php-directory-ini-settings/) compatible


```
sudo apt-get install newrelic-php5
```

After that there are [few settings](https://docs.newrelic.com/docs/apm/agents/php-agent/configuration/php-agent-configuration/) to set in 
- php.ini, 
- fpm-www pool conf 
- nginx fastcgi location
- [user.ini script per directory](https://docs.newrelic.com/docs/apm/agents/php-agent/configuration/php-directory-ini-settings/)

End result is ability to have multiple apps.

```
newrelic.appname = api1.myapp.com
newrelic.license = 123456
```

Verify installation with `<?php phpinfo(); ?>`

Start tracking transactions.
```
$name = 'v1/controller/action';
if (extension_loaded('newrelic')) {
    newrelic_name_transaction($name);
}
```

## Advanced newrelic settings

NewRelic agent
```
;built-in support for symfony1,2,4,yii,zend,zend2,drupal ...
newrelic.framework

;enforce sending data over HTTPS, 
;never send SQL query arguments "select * from names where id = ?", obfuscation before sending
;user-friendly HOSTNAME ie. api1.internal.myapp.com
newrelic.process_host.display_name
```

NewRelic collection daemon 
```
;egress proxy to send traffic thru for security paranoid setups
newrelic.daemon.proxy
```

Logs collector
```
newrelic.application_logging.enabled = true
newrelic.application_logging.metrics.enabled = true
newrelic.application_logging.forwarding.enabled = true
```

## NewRelic NRQL - metrics query language

NewRelic offers access to RAW data they collect via language called [NRQL] (https://docs.newrelic.com/docs/query-your-data/nrql-new-relic-query-language/get-started/introduction-nrql-new-relics-query-language/) similar to PromQL of Prometheus

With NRQL you can
- create own dashboards
- create own metrics (see prometheus recording rules)
- create own alerts (see prometheus alerts)

[![NewRelic NRQL 1](/images/newrelic/nrql-1.png)](/images/newrelic/nrql-1.png)
[![NewRelic NRQL 1](/images/newrelic/nrql-2.png)](/images/newrelic/nrql-2.png)


## Certification and 3rd party integrations

- [AWS Partner](https://newrelic.com/partners/aws-monitoring)
- Kubernetes Monitoring 
- Synthetics (external healthchecks)
- 500+ [Marketplace integrations](https://newrelic.com/instant-observability/)
  - [OpenTelemetry](https://newrelic.com/solutions/opentelemetry)
  - Golang
  - Linux, AWS ECS, Apple MacOS
  - MySQL
  - Nginx
  - RabbitMQ
  - Redis
  - GCP services
  - AWS services
  - Prometheus
  - JIRA/EMAIL/SLACK and generic WebHooks

## More examples

* [NewRelic APM - PHP summary](/images/newrelic/int/newrelic-summary-php.png)
* [NewRelic APM - PHP summary spike](/images/newrelic/int/newrelic-summary-php-spike.png)
* [NewRelic APM - Python summary](/images/newrelic/int/newrelic-summary-python-fastapi.png)
* [NewRelic APM - DB MySQL](/images/newrelic/int/newrelic-databases-mysql.png)
* [NewRelic APM - Redis](/images/newrelic/int/newrelic-databases-redis.png)
* [NewRelic APM - Errors](/images/newrelic/int/newrelic-summary-errors.png)
* [NewRelic APM - Browser](/images/newrelic/int/newrelic-browser-monitoring.jpg)


* [NewRelic Event - Demo M.E.L.T](https://newrelic.com/events/2021-07-29/2021-07-29-live-demo-improve-performance-reliability-and-scale-with-new-relic-apac)
* [NewRelic MELT 101](https://newrelic.com/platform/telemetry-data-101) 

#### Update Oct 20202
* Initial release
