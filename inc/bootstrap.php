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

// setup our configuration hook here
$app->on('__CONFIG__', function() {
    return [
        'core' => [
            'output.compression'      => false,
            'require.user.agent'      => false,
            'redirect.old.namespaces' => true,
            'require.https'           => false,
            'force.user.language'     => 'en',
            'enable.rate.limiting'    => false,
        ],
    ];
});