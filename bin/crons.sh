#!/usr/bin/env bash

cd /var/www
bin/console cleanup:sessions
bin/console statistics:collect
bin/console statistics:dashboard:update
