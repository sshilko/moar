---
layout: post
title: Fast Autoloading Classes in PHP
---

#### Fast Autoloading Classes in PHP

Cheatsheet, steps to better autoloading

1. [OPcache](http://php.net/manual/en/book.opcache.php)
2. [composer](https://getcomposer.org) classmap dump
3. Tune your file/path caches
4. Hybrid PSR-0 autoloader
5. Runtime classmap generator

#### OPcache

{% highlight bash %}
opcache.enable_file_override=1
opcache.memory_consumption=96
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=7963
opcache.save_comments=1
opcache.consistency_checks=0
opcache.enable=1
opcache.optimization_level=0xFFFFFFFF
opcache.huge_code_pages=0
{% endhighlight %}

Tune your production OPcache to ensure persistent opcache w/o invalidation

{% highlight bash %}
opcache.revalidate_freq=300
opcache.validate_timestamps=0
opcache.blacklist_filename=/some/path/to/blacklist_file
{% endhighlight %}

Invalidate & recompile OPcache on each deploy ONLY for files you modified, see

* [opcache_get_status](http://php.net/manual/en/function.opcache-get-status.php)
* [opcache_invalidate](http://php.net/manual/en/function.opcache-invalidate.php)
* [opcache_compile_file](http://us1.php.net/manual/en/function.opcache-compile-file.php)
* [php-fpm-cli](https://gist.github.com/muhqu/91497df3a110f594b992)

{% highlight bash %}
#!/bin/bash

# cd "$(dirname "$0")";
# http://mywiki.wooledge.org/BashSheet#Parameter_Operations
cd ${0%/*};

printf "\n";

find $PWD/.. \( -iname "*.php" -o -iname "*.tpl" -o -iname "*.ini" \) -type f -mmin -10 | \
xargs -n1 -I % ./php-fpm-cli -connect '/var/run/php5-fpm.sock' -r \
"echo 'PHP OPCODE CACHE';
echo (opcache_get_status(FALSE) && opcache_get_status(FALSE)['opcache_enabled']) ? (
    (opcache_invalidate('%') && opcache_compile_file('%')) ? 'REFRESHED %' : 'FAILED OPCODE INVALIDATION %'
) : 'OFF OR DONE %';print \"\n\";";

printf "\n";

{% endhighlight %}


#### Composer
{% highlight bash %}
composer dump-autoload -o
{% endhighlight %}

#### (real) path cache

Each time code does [include](http://php.net/manual/en/function.include.php) or require, 
include_path is used to try to find file (unless its absolute path), that means if the needed
file is in the last folder in your include path, it will take the longest time to find it.
So keep your include path's short (1 or 2 paths) and keep all your files organized if you use relative path's.

{% highlight bash %}
realpath_cache_size=256k
realpath_cache_ttl=86400
include_path=.
user_ini.cache_ttl=86400
{% endhighlight %}

#### Hybrid PSR-0 autoloader

Another thing u could do is, if you have a library, lets say [zendframework](https://github.com/zendframework/zf1) 
in (composer/zendframework/) instead of loading whole composer with all your packages, you should do hybrid autoloader

Instead of loading whole composer classmap, just put a symlink into your include_path to your zendframework,
if library supports PSR-0 ofc.

{% highlight bash %}
ln -s composer/zendframework/zendframework1/library/Zend mylibs/Zend
{% endhighlight %}

And we could also modify PSR-0 autoloader to work with composer more efficiently, and ONLY include composer
if class is not found without our own libraries (u dont need 100% of our composer libs on EVERY request)

* optional classmap feature
* lazy composer include
* PHP-7 fix & error_reporting level side-effect fix

{% highlight bash %}

/**
 * Hybrid PSR-0 Autoloader based on PHP-FIG
 * http://www.php-fig.org/psr/psr-0/
 * @author Sergei Shilko <contact@sshilko.com>
 */
$classMap = []; 
spl_autoload_register(function (string $className) use (&$classMap) {
    if (isset($classMap[$className])) {
        return include $classMap[$className];
    }

    $oname     = $className;
    $className = ltrim($className, '\\');
    $fileName  = '';
    if ($lastNsPos = strrpos($className, '\\')) {
        $namespace = substr($className, 0, $lastNsPos);
        $className = substr($className, $lastNsPos + 1);
        $fileName  = str_replace('\\', DIRECTORY_SEPARATOR, $namespace) . DIRECTORY_SEPARATOR;
    }
    $fileName .= str_replace('_', DIRECTORY_SEPARATOR, $className) . '.php';
    $filePath = stream_resolve_include_path($fileName);

    if ($filePath) {
        $classMap[$oname] = $filePath;
        /**
         * some frequent packages are directly mapped there via PSR-0
         * to prevent full composer autoloaded
         */
        return include_once $filePath;
    } else {
        /**
         * Only need autoloader once
         */
        if (!isset($GLOBALS['composer_autoloaded'])) {
            $nativeErrorLevel               = error_reporting();
            $GLOBALS['composer_autoloaded'] = true;
            $loader                         = require __DIR__ . '/../composer/autoload.php';
            /**
             * PHP7 fix for first-time composer autoload -->
             */
            $loader->loadClass($oname);
            /**
             * PHP7 fix for first-time composer autoload <--
             */
            if ($nativeErrorLevel != error_reporting()) {
                /**
                 * Bad packages broke the error levels, alert that and restore level
                 */
                error_log('Autoloader classes broke error_reporting(): ' . $nativeErrorLevel . ' != ' . error_reporting());
                error_reporting($nativeErrorLevel);
            }
        }

        return false;
    }
});

{% endhighlight %}


#### Runtime classmap generator

To get the last bits of performance (±1ms) having a classmap for your own classes w/o inventing a classmap generator
for your own (legacy) code, just autogenerate map on-fly

* generates classmap on-demand
* plug-in into hybrid autoloader

{% highlight bash %}

$classMapFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'someprefix_' . 'runtime_classmap.php';
$classMap     = [];
if (is_readable($classMapFile)) {
    $classMap = include($classMapFile);
    if (!is_array($classMap)) {
        $classMap = [];
    }
}
$classMapSize = count($classMap);

register_shutdown_function(function () use (&$classMap, $classMapFile, $classMapSize) {
    if (count($classMap) > $classMapSize) {
        clearstatcache();
        if ($newMapFile = tmpfile()) {
            $metaDatas  = stream_get_meta_data($newMapFile);
            if ($metaDatas['uri']) {
                $newMap = '<?php return ' . var_export($classMap, true) . ';';
                if (strlen($newMap) == fwrite($newMapFile, $newMap) && fflush($newMapFile)) {
                    @copy($metaDatas['uri'], $classMapFile);
                    if (md5_file($metaDatas['uri']) == md5_file($classMapFile)) {
                         if (function_exists('opcache_invalidate') && function_exists('opcache_compile_file')) {
                             opcache_invalidate($classMapFile, true);
                             opcache_compile_file($classMapFile);
                         }
                         chmod($classMapFile, 0644);
                    }
                }
            }
            fclose($newMapFile);
        }
    }
});

{% endhighlight %}

#### Update Dec 2016
 * Updated register_shutdown_function for high concurrent reads&writes

#### Update Mar 2017
* Additionnal checks for written files consistency

#### Update Dec 2020
* Fix for include_once in case file was included by some external loaders already
