#!/bin/bash

status=0

cd $(dirname $0)/..
files=$(find src -name "*.php")
for file in $files; do
    php -l $file
    if [[ $? > 0 ]]; then
        status=255
    fi
done

exit $status
