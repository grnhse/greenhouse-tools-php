<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

$root       = realpath(__DIR__ . '/..');
$library    = "$root/src";
$tests      = "$root/tests";
$path       = array($library, $tests, get_include_path());

set_include_path(implode(PATH_SEPARATOR, $path));
$vendorFilename = dirname(__FILE__) . '/../vendor/autoload.php';

require $vendorFilename;
