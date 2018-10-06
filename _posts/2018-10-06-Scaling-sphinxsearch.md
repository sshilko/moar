---
layout: post
title: Scaling sphinxsearch
---

#### Scaling sphinxsearch

[Sphinxsearch](http://sphinxsearch.com) is an open-source search server that is in production for more than 10 years,
 it is stable, efficient and simple to use. I will cover some scaling and setup details that are not so obvious
I wanted to share some good practices, they are easy to find but having them all in one place
, because some information is hidden deep inside documentation, some on forums and some known only after years of using in production.
I would recommend anyone who needs plug&play solution for fulltext/autocomplete search and also tag/recommendation based search to 
try out sphinx.

#### Simple setup

Lets start with simple setup and evolve it into scalable configuration.

Initial exaple Dockerfile for our sphinxsearch is Alpine Linux 3.8

{% highlight bash %}
FROM       alpine:3.8
STOPSIGNAL SIGTERM
RUN        addgroup -g 1111 -S dalek && adduser -u 1111 -S -G dalek dalek
RUN        apk add --no-cache sphinx sudo && \
           rm -rf /tmp/*                  && \
           rm -rf /var/cache/apk/*
EXPOSE     9312
EXPOSE     9212
ARG        config
RUN        mkdir -p /var/run/sphinxsearch
COPY       COPY/sphinxsearch.conf.tmp  /etc/sphinx/sphinx.conf
COPY       COPY/docker-entrypoint.sh   /docker-entrypoint.sh
RUN        chown dalek:dalek /var/run/sphinxsearch
RUN        chown dalek:dalek /var/lib/sphinx && rm -rf /var/lib/sphinx/*
RUN        echo "dalek ALL=(ALL) NOPASSWD: ALL" >> /etc/sudoers
USER       dalek
ENTRYPOINT ["/docker-entrypoint.sh"]
CMD        ["searchd", "--nodetach"]
{% endhighlight %}

Already some highlights
- SIGTERM as stopsignal, because it is "searchd" daemon official graceful shutdown signal
- Do not run as root, it is not required, so we use random user id=1111, name=dalek
- We EXPOSE two ports because "searchd" can listen on multiple ports, and later in your app you can load balance using that
- /var/lib/sphinx is data directory and /var/run/sphinxsearch is pid directory, must have correct permissions
- We copy sphinxsearch.conf.tmp as our sphinx config into default location for configuration, usually config is auto-generated from template (i.e. per environment)

#### Entrypoint

We need to launch searchd daemon without forking/detaching and pass execution flow to that process, use common docker-entrypoint bolerplate to create
this initial entrypoint script

- If we found config, we run indexer which tries to create/index all defined indexes in config file
- Also launch cron with sudo, if we want to re-index the indexes later on

{% highlight bash %}
#!/bin/sh
set -e

# If the sphinx config exists, try to run the indexer before starting searchd
if [ -f /etc/sphinx/sphinx.conf ]; then
    indexer --all > /dev/console
fi

#launch crond daemon in background with disabled logs
#It has to run as root unfortunatelly 
#(unless 3rd party cron is used https://github.com/aptible/supercronic)
sudo crond -l 6 -d 6 -b -L /dev/console

#launch sphinx
exec "$@"
{% endhighlight %}

#### sphinx.conf

Here is example configuration containing some products index from MySQL source

- In Sphinx integer attributes size can be defined to further optimize space/memory usage
- Use index and source inheritance and autogenerate configs programatically
- sql_query_pre can contain temporary table creation or other preparation for main query (db optimizer settings)
- Indexer memory limit needs to be different per production/test - another reason for autogenerated configs,
 because the memory is always reserved even if not needed (i.e. index data could be 1mb but whole amount is locked)
- You generally dont want query_log, and do want some increased defaults for max_filter_values and seamless_rotate = 1
- Workers could be threads/fork/prefork, realtime indexes only work with threads, also max max_children only has effect if non threads is selected
 the actual sources of sphinxsearch is best documentation of how exactly those settings work

{% highlight bash %}
# Custom attribute sizes (bytes to unsigled int's, (possible values = `2^(bits)-1`) <--
#1  bits  = 2 [can have only 2 values (0 or 1)]
#2  bits  = 3
#3  bits  = 7
#4  bits  = 15
#5  bits  = 31
#6  bits  = 63
#7  bits  = 127
#8  bits  = 255
#9  bits  = 511
#10 bits  = 1023
#Z  bits  = (2^Z)
# Custom attribute sizes (bytes to unsigled int's, (possible values = `2^(bits)-1`) <--

##
# DATABASE INDEX SOURCE -->
##
    source options
    {
        type            = mysql
        sql_port        = 3306

        # MySQL specific client connection flags
        # optional, default is 0
        # enable compression
        mysql_connect_flags = 32
    }
##
# DATABASE INDEX SOURCE <--
##
    #We extend option source, and inherit type,port and all other options
    #all sql_query_pre are inherited if not defined in this source, if at least 1 defined nothing is inherited
    source books_localhost_src : options
    {
        sql_host        = books-mydbhost.com
        sql_user        = mydbuser
        sql_pass        = mydbpass
        sql_db          = mydbname

        #Every time indexer needs to index, in creates new connection
        #This is executed after the connection is established, before sql_query
        sql_query_pre   = SET NAMES utf8mb4
        sql_query_pre   = SET SESSION query_cache_type=OFF

        sql_query       = \
                        SELECT books.id,                                               \
                               books.author_id,                                        \
                               books.timestamp_added,                                  \
                               books.timestamp_changed,                                \
                               books.available                                         \
                          FROM books                                                   \
                          
        sql_attr_uint      = author_id
        sql_attr_timestamp = timestamp_added
        sql_attr_timestamp = timestamp_changed
        sql_attr_uint      = available:1

        sql_attr_multi    = uint books_prices from query; SELECT book_id, price_int FROM books_prices WHERE price_int IS NOT NULL
    }                          

    index books_localhost_idx
    {
        source              = books_localhost_idx
        path                = /var/lib/sphinx/books_localhost_idx
        docinfo             = extern
        preopen             = 0
        # need concrete match, ignore morphology
        morphology          = none
        # memory locking for cached data (.spa and .spi), to prevent swapping
        mlock               = 0
        # index all (words of 1 char len)
        min_word_len        = 1
        type                = plain
    }

    indexer
    {
        #IMPORTANT while running, indexer will reserve this amount of memory even if its not needed
        #So if we run multiple indexers in parallel its easy to run out of memory
        mem_limit           = 128M
    }

    searchd
    {
    
        listen              = 9313:sphinx
        listen              = 9213:sphinx
    
        log                 = /dev/null
        #query_log          = /tmp/sphinx_query.log
        read_timeout        = 5
        pid_file            = /var/run/sphinxsearch/searchd.pid
        #max_matches        = 1000
        #@see http://sphinxsearch.com/forum/view.html?id=5099 (invalid attribute set length)
        max_filter_values   = 8192
    
        seamless_rotate     = 1
        preopen_indexes     = 0
    
        unlink_old          = 1
        workers             = threads # for RT to work
        binlog_path         = /var/lib/sphinx/
        client_timeout      = 20
        #maximum amount of children to fork (concurrent searches to run)
        max_children        = 1000
        attr_flush_period   = 0
        mva_updates_pool    = 32M
        listen_backlog      = 10
        
        #Affects distributed local indexes on multi-cpu/multi-core box (recommended 1xCPUs count)
        dist_threads        = 4
        
        query_log_format    = sphinxql
        prefork_rotation_throttle = 100
        binlog_flush        = 0
    }
{% endhighlight %}

#### Delta setup

Now if you have hundreds of millions of books, and your data changes only insignificantly every day (books are added or removed or made unavailable),
then good approach is to have a scheme of main + delta index, where main index is calculated on boot, while delta is calculated
every hour/day and just updates main index.

Using this setup requires more advanced knowledge of sphinx configuration options and internals, especially if you 
serve more than one index and more than one delta index on a single searchd daemon.

I would NOT RECOMMEND SERVING delta index directly and will not give example of that, as the merge tool exists and serving 
delta is just more overhead to searchd daemon while priority should be maximum performance on main indexes, so less memory and less locks are essential.

Instead `indexer merge` should be used to merge delta results into main index once ready.

#### Links

- [Sphinx 2.2.11-release reference manual](http://sphinxsearch.com/docs/current.html)
- [Delta updates](http://sphinxsearch.com/docs/current.html#delta-updates)
- [Local index in distributed declaration](http://sphinxsearch.com/docs/current.html#conf-local)
- [Handling duplicate document-ids](http://sphinxsearch.com/blog/2010/03/15/the-clone-wars-how-sphinx-handles-duplicates/)
- [Delta indexing example](https://qavi.tech/sphinx-search-delta-indexing/)
- [Sphinx tricks](http://sphinxsearch.com/blog/2013/11/05/sphinx-configuration-features-and-tricks/)
- [Sphinx index file formats](https://github.com/sphinxsearch/sphinx/blob/master/doc/internals-index-format.txt)
- [Sphinx sources](https://github.com/sphinxsearch/sphinx/blob/master/src/searchd.cpp)
- [Sphinx in docker](http://sphinxsearch.com/blog/2014/07/07/sphinx-in-docker-the-basics/)