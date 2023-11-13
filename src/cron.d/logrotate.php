#!/usr/local/bin/php -f
<?php

$dir = __DIR__ . "/../logs.d/";
$date = date("Y-m-d-G-i-s");
exec("cp $dir/wrapper.log $dir/wrapper.$date.log");
exec("echo -n > $dir/wrapper.log");
