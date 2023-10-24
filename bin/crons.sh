#!/usr/bin/env bash

cd /var/www || exit 1
bin/console cleanup:sessions
bin/console cleanup:containers
bin/console statistics:collect
bin/console statistics:dashboard:update
