---
layout: post
title: Messaging migration from Redis to DynamoDB
---

#### Migrating Messaging store from Redis to DynamoDB

At [Tandem](https://www.tandem.net) as part of many user features application has, there is Messaging.
Messaging allows standard functionality like:

* having list of opponents you exchanged messages with
* sending/receiving messages
* reading thru conversations history
* realtime notifications

The design of the messaging feature stayed stable and scaled from thousand of users in 2015 to millions of users 
using it now.
Every year part of messaging system that was storing the opponents list required maintenance and downtime.

When designing the data storage the application is using both 
* SQL (RDS)
* NoSQL (AWS Elasticache)

SQL for long-time storage, and Redis for fast hot-cache.

#### Issues with Redis

While amount of users that use messaging constantly increases, we found some issues with using Redis

* Storage of Redis is memory, and only way to scale memory is upgrading instance type
* Cost of upgrade doubles every time, and it looked like we need to upgrade every 6-9 months
* Some data we store in redis was rarely accessed, and less than 50% of data was really "hot"
* We are paying for 100% of data as if it was "hot" data

#### Goal

As we saw those patterns in usage, we realized that we are paying for the functionality we dont use, so we started 
to look for alternative storage

* Storage size scales without service interruption
* Low latency reads/writes
* Simple access patterns close to key-value database
* Managed service, ideally on AWS
* Lower cost TCO compared to AWS ElastiCache

There was one service that we think fits the requirements

* Scales storage
* Originally key/value store, but now with many "features" and indexes
* Allows for using SortedSets, Sets, Key/Value access patterns on it
* Pay per use, not per provisioned capacity, cheaper than scaling ElastiCache
* Available on AWS and well documented

This service was AWS DynamoDB.

#### Migration

We reproduced our NoSQL schema, previously
* Redis SortedSet
* Redis Keys

into just one DynamoDB table

* Primary key is "Hash and Range" key (String, String)
* 4 total attributes only
* 1 LSI (Local Secondary Index) for sorting by timestamp

| owner-id | opponent-id | metadata | timestamp |
|---|---|---|---|
| 1,a | 2,b | ... | 1613725769  |

Codebase change was relatively simple the storage to Redis was abstracted via interface.
We implemented new storage adapter that complies with same interfaces.

For zero-downtime migration, we first implemented Composite adapter that was replicating
all changes to both Redis and DynamoDB, so this way all the "active" data was replicated all the time.

In background we launched jobs that will migrate 100% of data from Redis to DynamoDB in (non active data).

#### Launch and metrics

Two months, 30GB and 240 million dynamodb "item count" later, 
after all background migrations finished, we now swapped the Composite adapter for DynamoDB adapter only.

New DynamoDB
* 1 table
* >400 rCU
* >500 wCU  
* 4ms Put latency
* 7ms Query latency
* 300 query returned item count average

Old Redis 5.0
* ~1..2ms latency
* 8 CPU, 52GB RAM, ~600$/month
* 3% CPU usage
* 99.7% Memory usage
* 95% cache hit rate
* connected_clients:1278
* 15000 GET / 100 000 SET type commands
* 15000 SortedSet commands
* db0:keys=153161846

Overall API response times.
![migration-1](/images/dynamoredis/migration-1.jpg)

DynamoDB response times.
![migration-2-dynamodb-external](/images/dynamoredis/migration-2-dynamodb-external.png)

Affected messaging API for message opponents list.
![migration-3-api-opponent-list](/images/dynamoredis/migration-3-api-opponent-list.png)

#### Summary

- We change our core database for one of most active/loaded service seamlessly and without downtime
- The latency did increase from ~33ms to ~87ms on API response, but hardly noticeable for end-users
- We now have very scalable storage and pay-per-use model, we dont need to do capacity planning and worry about downtimes
- We moved ~50Gb data from Redis in-memory to ~30GB DynamoDB items
 
#### Links

- [AWS DynamoDB](https://aws.amazon.com/dynamodb)
- [AWS ElastiCache](https://aws.amazon.com/elasticache/)