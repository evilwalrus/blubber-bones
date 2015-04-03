<?php

$path = dirname(__FILE__) . DIRECTORY_SEPARATOR;

// get all required hooks
foreach (glob($path . 'hooks/*.php') as $file) {
    require_once $file;
}

// get all required routes
foreach (glob($path . 'routes/*.php') as $file) {
    require_once $file;
}
