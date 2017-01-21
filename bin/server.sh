#!/bin/sh

cd `dirname $0`
cd ..

php -S localhost:8811 -t public/ bin/router.php
