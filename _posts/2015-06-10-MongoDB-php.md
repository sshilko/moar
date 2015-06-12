---
layout: post
title: Evaluating MongoDB as key value/document store
---

#### Introduction

[MongoDB](https://www.mongodb.org/) is a [Document Store](http://nosql-database.org/), currently used it as a key-value (document) storage.

* MongoDB API: BSON,
* Protocol: C, Query Method: dynamic object-based language & MapReduce,
* Replication: Master Slave & Auto-Sharding,
* Written in: C++,
* Concurrency: Update in Place.
* Misc: Indexing, GridFS, Freeware + Commercial License

Few years ago (2013-2014) i needed simple solution for storing uniform-type objects, at that moment MongoDB was pretty popular
and got alot of reviews, everyone were *hyping the NoSQL/MongoDB ease of use*.

* [The DynamoDB](http://aws.amazon.com/documentation/dynamodb/) did not yet had secondary indexes and was called SimpleDB
* [Redis](http://redis.io/) did not yet had clustering solution
* Other NoSQL solutions looked not as mature/production-ready as MongoDB.

So i gave Mongo a try, installed 2.4 version and tested it with bulk-inserts & reads, the end-result looked pretty good

* High insert rate (compared to MySQL)
* Fast reads
* Plug-and-play installation on Ubuntu/Server with minimum settings (authentification and etc.).

What i needed is a **simple persistent document storage** to store my json objects, w/o any analytics or reporting requirements.
I installed Mongo on AWS EC2 instance according to [official documentation](http://docs.mongodb.org/v2.4/administration/production-notes/).
And so about year passed...

#### DB Grows stronger

&nbsp;  | &nbsp;
--------- | ----
Avg. Object Size | 115 Bytes
Objects   | 23+ Millions
StorageSize | 3Gb
Collections | 4
Indexes     | 3
Index Size  | 950Mb (Data Size = 2.5Gb)
AverageCursors | 300
BackgroundFlushTime &nbsp;&nbsp;| ~9ms
&nbsp;  | &nbsp;


#### First results

Week ago, while looking at performance metrics i saw MongoDB [MongoCursor::next](http://php.net/manual/en/mongocursor.next.php) taking up to 10 seconds.

![MongoCursor](/images/mongo3.jpg)


What the [newrelic](https://newrelic.com/) shows is **sometimes** fetching the data took 6,4 seconds!

#### Fix #1: Updating the environment software

As the software were not updated for last year, i did updated the server first thinking that would fix the issue

1. Updated the [mongo pecl](https://pecl.php.net/package/mongo) extension from 1.5.5 to 1.6.8
2. Updated the mongo installation from 2.4.12 to 2.6.10
3. Updated PHP to 5.5.25
4. Upgraded AWS EC2 instance to double the memory and ECU (cpu)

#### Fix #2: Updating the application software

Easy #1 fix didnt do much, the requests still randomly took up to 5 seconds.


Then its time to refactor the application code, that came down to this code here

{% highlight php %}
<?php
//...

/**
 * @link http://www.php.net/manual/en/mongocollection.find.php
 */
$realCursor = $collection->find(array('_id' => array('$in' => $mongoIds));
$realCursor->timeout(5000);
$realCursor->hint(array('_id' => 1));

/**
 * Try fetch everything in one server round-trip (but cannot fetch more than 4 megabytes anyway)
 * Also pre-set known limit: that removes the need to send a request to close the cursor server-side
 *
 * @see http://docs.mongodb.org/manual/reference/method/cursor.limit/
 * @see http://php.net/manual/de/mongocursor.batchsize.php
 */
$expectedLimit = count($mongoIds);
$realCursor->batchSize($expectedLimit);
$realCursor->limit($expectedLimit);

/**
 * slow fetch 2-5s
 *
 * PHP 5.5.25
 * Pecl Mongo driver 1.6.8
 * MongoDB 2.6.10
 * @see https://pecl.php.net/package/mongo
 *
 */
$arrayData = iterator_to_array($realCursor, false);

/**
 * If you call reset() on a cursor and never call hasNext/getNext/etc it will essentially act as a "close" method.
 * This is useful if you didn't finish fetching all the data from a cursor
 * but don't want it to linger around till MongoDB decides to kill it.
 */
$realCursor->reset();

//...
?>
{% endhighlight %}

> After code was optimized 1-roundtrip fetch by _id (primary index) **still took 2 to 5 seconds**

#### Fix #3: Reading manuals

Obviously something else were causing the lag/delays in queries, and what that can be

1. Fsync/Physical disk access
2. CPU spikes/EC2 CPU stolen cycles
3. Database locks
4. ...

Quickly looking at AWS Cloudwatch metrics showed <5% CPU usage, there were plenty of RAM free and disk usage was near zero.
Quickly googling 'mongo monitoring' and looking into docs the [MongoDB MMS](https://mms.mongodb.com) was found,
account was registered (its free) and monitoring agent installed.

![MMS1](/images/mongo1.jpg)

MMS Service didnt gave any reasons for query to take so long :(
Going back to manuals the only clue left were "Locks"

>What type of locking does MongoDB use?

>MongoDB uses a readers-writer [1] lock that allows concurrent reads access **to a database** but gives **exclusive access to a single write operation**.
When a read lock exists, many read operations may use this lock.
However, **when a write lock exists, a single write operation holds the lock exclusively, and no other read or write operations may share the lock**.
http://docs.mongodb.org/v2.6/faq/concurrency/

* **MongoDB 2.6 has *database-level locks***, and MongoDB 3.0 has collection-level locks.

Only [latest](http://docs.mongodb.org/manual/faq/concurrency/) [wiredTiger](https://www.mongodb.com/blog/post/welcome-wiredtiger-mongodb) engine has document-level locks

>MongoDB uses multi-granularity locking [1] that allows operations to lock at the global, database or collection level, and allows for individual storage engines to implement their own concurrency control below the collection (i.e., at the document-level in WiredTiger).

#### Fix #4: Locks

Looking into [Shanty_Mongo_Document](https://github.com/coen-hyde/Shanty-Mongo) code i did found that the library uses

> db.collection.update together with [upsert](http://docs.mongodb.org/manual/reference/method/db.collection.update/) option
>
Optional. If set to true, creates a new document when no document matches the query criteria. The default value is false, which does not insert a new document when no match is found.
>
and [safe](http://docs.mongodb.org/v2.6/core/write-concern/) option to ensure data is written to journal

{% highlight php %}
<?php
//...

//$collection->update($where, $what, array('upsert' => true, 'safe' => true));

//replaced with

$collection->insert($what, array('socketTimeoutMS' => 5000,
                                 'w'               => 1));
{% endhighlight %}

#### Fix #5 update vs insert

Replacing the update+upsert option with write-safe insert, and the long-running read/find queries are gone.

Nothing changed much on stats, but newrelic no longer traces the long-running 2-6 second fetch queries from MongoDB.

Looking at Opcounters, the overall number of "Commands" is the same, but "Query" and "Insert" numbers are lower.

*Just a guess but maybe single "upsert" is translated/executed as 2 operations internally*

1. Query if key exists
2. If exists, update, else insert.

and thats why replacing it with *more simple* insert reduced the locks.

> the query/fetch still sometimes takes up to 800ms, but much less.

![MongoCursor](/images/mongo2.jpg)


#### Last words

* MongoDB is easy to install but a mystery to maintain and debug
* Different MongoDB (2.2, 2.4, 2.6, 3.0) versions have **significant** implementation specifics/differences
* Latest wiredTiger [looks promising](http://docs.mongodb.org/manual/faq/concurrency/) compared to Collection-locking in current MMAPv1
* Many database aspects are hidden in [FAQ](http://docs.mongodb.org/manual/faq/) Read carefully! Dont trust landing page ads ;)
* MongoDB introduces eventual consistency (some analogy with [AWS S3 in US Standard Eventual consistency](http://aws.amazon.com/s3/faqs/)) as a way to handle large amounts
  of data, is not in any scenario a silver bullet solution, and would probably *best fit is where u dont need write-safe* operations like
  some logs collecting or IoT/Sensors, non-critical solution for distributing or sharing content
  that is durably stored elsewhere, or other processed data that can be easily reproduced.

> When inserts, updates and deletes have a weak write concern, write operations return quickly. In some failure cases, write operations issued with weak write concerns may not persist. [MongoDB: Write Concern](http://docs.mongodb.org/v2.6/core/write-concern/)


* I'am continuing using/evaluating the MongoDB and will surely try out the wiredTiger engine soon
