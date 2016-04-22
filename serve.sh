#!/bin/bash

#http://rvm.io/rubies/default
#/bin/bash --login
#rvm use default

jekyll serve -t -P 8080 -H 127.0.0.1 -b "" --config _local.yml
