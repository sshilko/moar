---
layout: post
title: Compiling PHP
---

#### Compiling PHP on Ubuntu x86_64

After going thru all of troubles with compiling PHP, which was actually a fun process that took around 4 days in total,
i wanted to share the how-to guide, for myself to remember and for other to have a reference.
This guide is not for beginners, so i will keep the story short by providing minimum guidance.

There are many different reasons why u would want to compile PHP from sources i.e.

* Curious how it works
* Your distribution doesnt support needed version, neither there 3rd party distro available
* Getting the last bits of performance by compiling it your way
* Beta testing against latest PHP features
* Building your own/company's distribution, because everyone has their special requirements
* Improving your software [CI](https://en.wikipedia.org/wiki/Continuous_integration)/[CD](https://en.wikipedia.org/wiki/Continuous_delivery) 
* Learning new stuff altogether

#### The goal

Our goal is to have our own stable redistributable package (deb) of custom PHP build, the steps would be

1. <a href='#read-docs-1'>Read docs</a>
2. <a href='#meet-dependencies'>Meet dependencies</a>
3. <a href='#fetch-php'>Fetch PHP</a>
4. <a href='#compile-php'>Compile PHP</a>
5. <a href='#compilephpize-extension'>Compile/phpize extension</a>
6. <a href='#generate-ini-files'>Generate INI files</a>
7. <a href='#build-deb-package'>Build deb package</a>

#### Read docs

The is alot of googling involved in the process, and to make life easier i references all the info at <a href='#docs'>the end of article</a>. 

#### Meet dependencies

Grab a Ubuntu 64 distro, and lets begin

{% highlight bash %}
update-alternatives --set editor /usr/bin/vim.basic

apt-get -y install \
libt1-dev \
libgmp-dev \
libcurl4-openssl-dev \
bison \
libxslt-dev \
libxml2-dev \
libxpm-dev \
libmcrypt-dev \
pkg-config \
libbz2-dev \
libpng-dev \
libfreetype6-dev \
libgmp3-dev \
libmysqlclient-dev \
libwebp-dev \
libjpeg-dev \
build-essential \
libtool \
software-properties-common \
libssl-dev \
autoconf \
git-core \
cmake

apt-get install pkg-config

add-apt-repository ppa:ubuntu-toolchain-r/test -y
apt-get update
apt-get install gcc-4.8 g++-4.8 -y
update-alternatives --install /usr/bin/gcc gcc /usr/bin/gcc-4.8 60 --slave /usr/bin/g++ g++ /usr/bin/g++-4.8

{% endhighlight %}

This installs my set of required packages for building [1].

If u need more, for example you need webp support for [GD extension](http://php.net/manual/en/book.image.php) for [imagewebp](http://php.net/manual/en/function.imagewebp.php) function then according to [GD documentation](http://php.net/manual/en/image.installation.php)
you will need to install 

* `As of PHP 7.0.0 --with-webp-dir=DIR has to be added`
* `apt-get install libwebp-dev`
* `--with-webp-dir=/usr`

And so on for other extensions not included in this guide.
Second, we will use gcc 4.8 according to this [gcc roadman](https://gcc.gnu.org/develop.html), its fresh enough.

#### Fetch PHP

Lets compile latest released version atm. [7.0.11](http://www.php.net/ChangeLog-7.php#7.0.11)

To make it more challenging and be real-world case, lets compile it with some extensions

* with one static extension [redis](https://pecl.php.net/package/redis)
* with one shared extension via phpize [amqp](https://pecl.php.net/package/amqp)
* and our amqp extension has another dependency (rabbitmq-c)[https://github.com/alanxz/rabbitmq-c] or `librabbitmq-dev`

{% highlight bash %}
cd ~

PHP_VERSION="7.0.11"
PHP_FULLVERSION="php-${PHP_VERSION}"
PHP_SOURCES_FILE="${PHP_FULLVERSION}.tar.gz"
PHP_SOURCES_LOCATION="http://de1.php.net/get/${PHP_SOURCES_FILE}/from/this/mirror"
PHP_EXT_REDIS_VERSION="3.0.0"

wget -O $PHP_SOURCES_FILE $PHP_SOURCES_LOCATION
tar zxf $PHP_SOURCES_FILE
cd $PHP_FULLVERSION

cd ext
wget https://pecl.php.net/get/redis-${PHP_EXT_REDIS_VERSION}.tgz
tar zxf redis-${PHP_EXT_REDIS_VERSION}.tgz
mv redis-${PHP_EXT_REDIS_VERSION} redis
cd ..

{% endhighlight %}

#### Compile PHP

Here we need to make alot of decisions, here is the end-result

{% highlight bash %}
PHP_ZEND_API="20151012"
PHP_INSTALL_ROOT="/usr/local/php7"
PHP_PREFIX_DIR="${PHP_INSTALL_ROOT}/${PHP_VERSION}"
PHP_DATAROOT="${PHP_PREFIX_DIR}/share"
PHP_LIB_DIR="${PHP_PREFIX_DIR}/lib"
PHP_BIN_DIR="${PHP_PREFIX_DIR}/bin"
PHP_EXTENSIONS_DIR="${PHP_LIB_DIR}/${PHP_ZEND_API}"

PHP_INI_CLI_DIR="${PHP_PREFIX_DIR}/etc"
PHP_INI_CLI_FILE="${PHP_INI_CLI_DIR}/php.ini"
PHP_INI_CLI_SCAN_DIR="${PHP_INI_CLI_DIR}/conf.d"
PHP_INI_CLI_EXTENSIONS="${PHP_INI_CLI_SCAN_DIR}/extension.ini"
PHP_INI_CLI_ZENDEXTENSIONS="${PHP_INI_CLI_SCAN_DIR}/zend_extension.ini"

PHP_INI_FPM_DIR="${PHP_INI_CLI_DIR}"
PHP_INI_FPM_FILE="${PHP_INI_FPM_DIR}/php-fpm.conf"
PHP_INI_FPM_SCAN_DIR="${PHP_INI_FPM_DIR}/php-fpm.d"

PHP_AMQP_RABBITMQ_LIBDIR="${PHP_LIB_DIR}/rabbitmq-shared"

export LANG="en"
export LC_ALL="en_US.UTF-8"
export CHOST="x86_64-linux-gnu"

SAFE_CFLAGS_1="-m64 -march=corei7 -mfpmath=sse -minline-all-stringops -pipe -fstack-protector -Wformat -Werror=format-security"
SAFE_CFLAGS_2="-fPIC -fPIE -fno-strict-aliasing -fsigned-char -std=gnu99 -mpc64 --param=ssp-buffer-size=4"

CFLAGS="\"-O3 ${SAFE_CFLAGS_1} ${SAFE_CFLAGS_2} "\"

CXXFLAGS=$CFLAGS

CMAKE_CFLAGS="-m64 -march=corei7 -O2 -fPIC"
{% endhighlight %}

We are building

* for intel 64bit cpu with `-march=corei7` [19] gcc flag
* for (debian amd64) or (gnu x86_64) which are the same (see `cat /usr/share/dpkg/cputable`)
* everything is installed under `/usr/local/php7`
* we generate code for a 64-bit environment only [19]
* we enable some optimizations like `-mfpmath=sse -minline-all-stringops` (-mfpmath=sse is default for x86-64 compiler [19])
* `-fstack-protector --param=ssp-buffer-size=4` is hardening option [30] for `GCC<4.9`, that emit extra code to check for buffer overflows
* `-fno-strict-aliasing` is performance option passed through to the link stage (34) (35), *disables* -O2 and -O3 `-fstrict-aliasing` (36)
* `-std=gnu99` enables C99 standard (23) (25), i.e. C code already contains `long long int` data types; also C99 brings inline functions and optimizations
* `-fPIC -fPIE` is similar to `-fpic -fpie` (37) (38) Generate position-independent code (PIC) & executable
* `-fsigned-char` Let the type char be signed, like signed char. (22)
* `-mpc64` rounds the significands of results of floating-point operations to 53 bits (double precision) vs 64bits (default)

We also define `CMAKE_CFLAGS` flags for later on.


Now as we have PHP sources, lets deal our extension dependency `librabbitmq`.
First we need to check if we dont have it installed `pkg-config librabbitmq --libs`.
Because (26) GCC first searches for libraries in `/usr/local/lib`, then in `/usr/lib`. 
Following that, it searches for libraries in the directories specified by the -L parameter, in the order specified on the command line.
If we do have `librabbitmq` library installed, GCC will link agains it ignoring our  library.

{% highlight bash %}
#REMOVE ANY PREINSTALLED PACKAGE (check `pkg-config librabbitmq --libs`)

RABBITMQ_C_VERSION="0.8.0"
RABBITMQ_C_SOURCES_LOCATION="https://github.com/alanxz/rabbitmq-c/archive/v${RABBITMQ_C_VERSION}.tar.gz"

rm -rf rabbitmq-c
wget -O rabbitmq-c.tar.gz ${RABBITMQ_C_SOURCES_LOCATION}
tar zxf rabbitmq-c.tar.gz
rm -f rabbitmq-c.tar.gz
mv rabbitmq-c-${RABBITMQ_C_VERSION}/ rabbitmq-c
cd rabbitmq-c
rm -rf build && mkdir build && cd build

rm -f CMakeCache.txt

cmake -DCMAKE_C_FLAGS="$CMAKE_CFLAGS" \
-DCMAKE_INSTALL_PREFIX=${PHP_AMQP_RABBITMQ_LIBDIR} \
-DBUILD_EXAMPLES=OFF \
-DBUILD_STATIC_LIBS=OFF \
-DBUILD_TESTS=OFF \
-DBUILD_TOOLS=OFF \
-DENABLE_SSL_SUPPORT=OFF \
-DBUILD_SHARED_LIBS=ON \
-DBUILD_API_DOCS=OFF ..

mkdir -p ${PHP_LIB_DIR}
cmake --build . --target install --clean-first

cd ../..
{% endhighlight %}

This will install our librabbitmq under our PHP root for linking later on.

{% highlight bash %}

rm -f configure && ./buildconf --force

CONFIGURE_STRING=" "\
"--prefix=${PHP_PREFIX_DIR} "\
"--bindir=${PHP_BIN_DIR} "\
"--sbindir=${PHP_PREFIX_DIR}/sbin "\
"--sysconfdir=${PHP_PREFIX_DIR}/etc "\
"--sharedstatedir=${PHP_PREFIX_DIR}/com "\
"--localstatedir=${PHP_PREFIX_DIR}/var "\
"--libdir=${PHP_LIB_DIR} "\
"--includedir=${PHP_PREFIX_DIR}/include "\
"--datarootdir=${PHP_DATAROOT} "\
"--infodir=${PHP_DATAROOT}/info "\
"--localedir=${PHP_DATAROOT}/locale "\
"--mandir=${PHP_DATAROOT}/man "\
"--docdir=${PHP_DATAROOT}/doc "\
"--with-config-file-path=${PHP_INI_CLI_DIR} "\
"--with-config-file-scan-dir=${PHP_INI_CLI_SCAN_DIR} "\
"--disable-all "\
"--without-pear "\
"--enable-cli "\
"--disable-cgi "\
"--disable-phpdbg "\
"--disable-debug "\
"--disable-rpath "\
"--with-layout=GNU "\
"--enable-fpm "\
"--enable-pdo "\
"--with-mysql-sock=/var/run/mysqld/mysqld.sock "\
"--with-mysqli=mysqlnd "\
"--with-pdo-mysql=mysqlnd "\
"--enable-mysqlnd "\
"--with-pic "\
"--with-pcre-regex "\
"--with-jpeg-dir=/usr "\
"--with-png-dir=/usr "\
"--with-xpm-dir=/usr "\
"--with-freetype-dir=/usr "\
"--enable-gd-native-ttf "\
"--enable-gd-jis-conv "\
"--disable-static "\
"--with-readline "\
"--with-fpm-user=www-data "\
"--with-fpm-group=www-data "\
"--enable-dom=shared "\
"--enable-mbstring=shared "\
"--enable-redis=shared "\
"--enable-opcache=shared "\
"--with-mhash=/usr "\
"--enable-phar=shared "\
"--enable-fileinfo=shared "\
"--with-gd=shared "\
"--enable-session "\
"--enable-hash "\
"--enable-json "\
"--enable-filter "\
"--enable-libxml "\
"--enable-ctype "\
"--enable-tokenizer "\
"--enable-pcntl "\
"--enable-posix "\
"--enable-xml "\
"--enable-xmlwriter "\
"--enable-bcmath "\
"--enable-simplexml "\
"--enable-exif "\
"--enable-shared "\
"--with-iconv "\
"--with-zlib "\
"--with-zlib-dir=/usr "\
"--with-libedit=/usr "\
"--with-curl "\
"--with-openssl=yes "\
"--with-pdo_mysql "\
"--build ${CHOST} "\
"--host ${CHOST} "\
"CC=gcc CFLAGS=$CFLAGS CHOST=$CHOST CXXFLAGS=$CFLAGS"

CFG_CMD="./configure ${CONFIGURE_STRING}"

eval $CFG_CMD;

make -j `cat /proc/cpuinfo | grep processor | wc -l`

make install

\cp php.ini-production ${PHP_INI_CLI_FILE}

{% endhighlight %}

Few notes on configure flags

* readline is required for correct cli work
* xml is required for [utf8_decode](http://php.net/manual/en/function.utf8-decode.php) & utf8_encode
* most of the libraries are compiled-in, while only few are built as shared (i.e. `--with-gd=shared`)

What extensions you wish to build as shared is up to you, here is a mine sizes reference table

{% highlight bash %}

##AVG Shared Extension sizes##
# 3.8M  sqlite3.so
# 3.5M  fileinfo.so
# 2.8M  mbstring.so
# 1.7M  redis.so
# 1.3M  gd.so
# 1.1M  phar.so
# 989K  dom.so
# 923K  opcache.so
# 835K  zip.so
# 525K  openssl.so
# 436K  amqp.so
# 368K  sockets.so
# 358K  bcmath.so
# 300K  curl.so
# 246K  gmp.so
# 224K  simplexml.so
# 193K  exif.so
# 186K  zlib.so
# 173K  iconv.so
# 139K  pdo_sqlite.so
# 122K  xmlwriter.so
# 110K  xmlreader.so
#  99K  bz2.so
#  98K  posix.so
#  95K  pcntl.so
#  72K  tokenizer.so
#  50K  ctype.so

{% endhighlight %}

In the end we get our php installed under `PHP_INSTALL_ROOT`.

{% highlight bash %}

$ /usr/local/php7/7.0.11/bin/php -n --ini
Configuration File (php.ini) Path: /usr/local/php7/7.0.11/etc
Loaded Configuration File:         (none)
Scan for additional .ini files in: (none)
Additional .ini files parsed:      (none)

$ /usr/local/php7/7.0.11/sbin/php-fpm -v
PHP 7.0.11 (fpm-fcgi) (built: Oct 13 2016 11:38:14)
Copyright (c) 1997-2016 The PHP Group
Zend Engine v3.0.0, Copyright (c) 1998-2016 Zend Technologies
    with Zend OPcache v7.0.11, Copyright (c) 1999-2016, by Zend Technologies

$ /usr/local/php7/7.0.11/bin/php -n -m
[PHP Modules]
bcmath
Core
ctype
curl
date
exif
filter
hash
iconv
json
libxml
mysqli
mysqlnd
openssl
pcntl
pcre
PDO
pdo_mysql
posix
readline
Reflection
session
SimpleXML
SPL
standard
tokenizer
xml
xmlwriter
zlib

[Zend Modules]
{% endhighlight %}

#### Compile/phpize extension

Now lets build `amqp` extension, we already built `librabbitmq-dev - An AMQP client library written in C - Dev Files`

{% highlight bash %}
AMQP_VERSION="1.7.1"

wget https://pecl.php.net/get/amqp-${AMQP_VERSION}.tgz
rm -rf amqp
tar zxf amqp-${AMQP_VERSION}.tgz
mv amqp-${AMQP_VERSION} amqp
cd amqp

export PKG_CONFIG_PATH="${PHP_AMQP_RABBITMQ_LIBDIR}/lib/${CHOST}/pkgconfig/"
export LD_LIBRARY_PATH="${PHP_AMQP_RABBITMQ_LIBDIR}/lib/"

/usr/bin/pkg-config librabbitmq --libs

make clean && rm -f configure
${PHP_BIN_DIR}/phpize .
CFG_CMD="./configure \
--with-php-config=${PHP_BIN_DIR}/php-config \
--with-amqp \
--with-pic \
--with-librabbitmq-dir=yes \
CFLAGS=${CFLAGS} \
CHOST=\"${CHOST}\" "
eval $CFG_CMD

make -j `cat /proc/cpuinfo | grep processor | wc -l`

make install
${PHP_BIN_DIR}/php -n -dextension=${PHP_EXTENSIONS_DIR}/amqp.so --ri amqp

cd ..
{% endhighlight %}

This should build `amqp.so` that is linked agains our `librabbitmq`

{% highlight bash %}
ldd ${PHP_EXTENSIONS_DIR}/amqp.so |grep librabbitmq
#2:	librabbitmq.so.4 => /usr/local/php7/7.0.11/lib/rabbitmq-shared/lib/x86_64-linux-gnu/librabbitmq.so.4 (0x00007f910d263000)
{% endhighlight %}

#### Generate INI files

We are still missing the default .ini files, lets fix that, and make sure all extensions have linked correctly

{% highlight bash %}

ldd ${PHP_EXTENSIONS_DIR}/* | grep 'not found'

mkdir -p "${PHP_INI_CLI_SCAN_DIR}"

rm -f ${PHP_EXTENSIONS_DIR}/*a

find \
${PHP_EXTENSIONS_DIR}/*so \
'!' -name 'opcache.so' \
| sort \
| sed "s|$PHP_EXTENSIONS_DIR|extension=$PHP_EXTENSIONS_DIR|" \
> "${PHP_INI_CLI_EXTENSIONS}"

find \
${PHP_EXTENSIONS_DIR}/opcache.so \
| sed "s|$PHP_EXTENSIONS_DIR|zend_extension=$PHP_EXTENSIONS_DIR|" \
> "${PHP_INI_CLI_ZENDEXTENSIONS}"

\cp ${PHP_INI_FPM_FILE}.default ${PHP_INI_FPM_FILE}
{% endhighlight %}

#### Build deb package

To share our package, lets create a simplest deb package (27) (28)

{% highlight bash %}

PHP_PACKAGE_DEB_NAME="mypackage-php-name"
PHP_PACKAGE_MAINTAINER="Sergei Shilko <contact@sshilko.com>"

cd ${PHP_PREFIX_DIR}/..
mkdir -p ${PHP_FULLVERSION}/DEBIAN
mkdir -p ${PHP_FULLVERSION}/${PHP_PREFIX_DIR}/
cp -rf ${PHP_PREFIX_DIR} ${PHP_FULLVERSION}/${PHP_PREFIX_DIR}/..

echo "Package: ${PHP_PACKAGE_DEB_NAME}
Version: ${PHP_VERSION}
Section: base
Priority: optional
Architecture: amd64
Depends:
Maintainer: ${PHP_PACKAGE_MAINTAINER}
Description: PHP" > ${PHP_FULLVERSION}/DEBIAN/control

dpkg-deb --build ${PHP_FULLVERSION}
mv ${PHP_FULLVERSION}.deb ${PHP_PACKAGE_DEB_NAME}-`date +"%F"`.deb
rm -rf ${PHP_FULLVERSION}
{% endhighlight %}

#### <a href='#docs' id='docs'>Literature</a>

1. [Building PHP](http://www.phpinternalsbook.com/build_system/building_php.html)
2. [Building PHP extensions](http://www.phpinternalsbook.com/build_system/building_extensions.html)
3. [Getting into the Zend Execution engine](http://jpauli.github.io/2015/02/05/zend-vm-executor.html)
4. [PHP's OPCache extension review](http://jpauli.github.io/2015/03/05/opcache.html)
5. [Compiling And Installing PHP7 On Ubuntu](http://www.hashbangcode.com/blog/compiling-and-installing-php7-ubuntu)
6. [How to install latest gcc on Ubuntu LTS (12.04, 14.04, 16.04)](https://gist.github.com/application2000/73fd6f4bf1be6600a2cf9f56315a2d91)
7. [How to compile php7 on ubuntu 14.04](http://jcutrer.com/howto/linux/how-to-compile-php7-on-ubuntu-14-04)
8. [PHP.NET - Installation on Unix systems](http://php.net/manual/en/install.unix.php)
9. [PHP.NET - List of core configure options](http://php.net/manual/en/configure.about.php)
10. [PHP.NET - Life cycle of an extension](http://php.net/manual/en/internals2.structure.lifecycle.php)
11. [PHP Extensions - What and Why by Derick Rethans](https://derickrethans.nl/talks/phpexts-zendcon11.pdf)
12. [Symfony Polyfill](https://github.com/symfony/polyfill/blob/master/README.md)
13. [GCC - clang: a C language family frontend for LLVM](http://clang.llvm.org)
14. [GCC - Using Hardening Options](https://wiki.debian.org/Hardening#Using_Hardening_Options)
15. [GCC - Stack Smashing Protector, and _FORTIFY_SOURCE](http://www.linuxfromscratch.org/hints/downloads/files/ssp.txt)
16. [GCC - Clang vs GCC (GNU Compiler Collection)](http://clang.llvm.org/comparison.html#gcc)
17. [GCC - Safe CFLAGS - Find CPU-specific options](https://wiki.gentoo.org/wiki/Safe_CFLAGS#Find_CPU-specific_options)
18. [GCC - Options That Control Optimization](https://gcc.gnu.org/onlinedocs/gcc-4.8.1/gcc/Optimize-Options.html)
19. [GCC - Intel 386 and AMD x86-64 Options](https://gcc.gnu.org/onlinedocs/gcc-4.8.2/gcc/i386-and-x86-64-Options.html#i386-and-x86-64-Options)
20. [GCC - Options for Code Generation Conventions](https://gcc.gnu.org/onlinedocs/gcc-4.8.2/gcc/Code-Gen-Options.html#Code-Gen-Options)
21. [GCC - Environment Variables Affecting GCC](https://gcc.gnu.org/onlinedocs/gcc-4.8.2/gcc/Environment-Variables.html#Environment-Variables)
22. [GCC - Options Controlling C Dialect](https://gcc.gnu.org/onlinedocs/gcc-4.8.1/gcc/C-Dialect-Options.html#C-Dialect-Options)
23. [GCC - Status of C99 features in GCC](https://gcc.gnu.org/c99status.html) (-std=gnu89 is default until gcc5 with -std=gnu11)
24. [GCC - best “general purpose” set of flags - Ben Eastaugh and Chris Sternal-Johnson](https://tombarta.wordpress.com/2008/05/25/gcc-flags/)
25. [GCC - 5 Release Series Changes, New Features, and Fixes](https://gcc.gnu.org/gcc-5/changes.html)
26. [GCC - Shared libraries with GCC on Linux](http://www.cprogramming.com/tutorial/shared-libraries-linux-gcc.html)
27. [DEB - dpkg-architecture - set and determine the architecture for package building](http://man7.org/linux/man-pages/man1/dpkg-architecture.1.html)
28. [DEB - Debian Policy Manual - Package maintainer scripts and installation procedure](https://www.debian.org/doc/debian-policy/ch-maintainerscripts.html)
29. [DEB - Maintainer scripts](https://people.debian.org/~srivasta/MaintainerScripts.html)
30. [DEB - Hardening](https://wiki.debian.org/Hardening)
31. [DEB - How to make a "Basic" .deb](https://ubuntuforums.org/showthread.php?t=910717)
32. [Pattern library](http://www.welie.com/patterns/)
33. [XKCD](http://xkcd.com)
34. [COMPILER OPTIMIZATION ORCHEST TION FOR PEAK PERFORMANCE](http://docs.lib.purdue.edu/cgi/viewcontent.cgi?article=1124&context=ecetr)
35. [Про C++ алиасинг, ловкие оптимизации и подлые баги](https://habrahabr.ru/post/114117/)
36. [GCC - Options That Control Optimization](https://gcc.gnu.org/onlinedocs/gcc-4.9.2/gcc/Optimize-Options.html)
37. [Position Independent Executable](http://www.openbsd.org/papers/nycbsdcon08-pie/)
38. [GCC - Options for Code Generation Conventions](https://gcc.gnu.org/onlinedocs/gcc/Code-Gen-Options.html)
