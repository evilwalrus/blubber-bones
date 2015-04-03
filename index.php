<?php

require_once 'lib/Blubber/autoloader.php';

// load Blubber and start processing
$app = new Blubber\App(['v1']);

// now load the hooks and routes
require_once 'inc/bootstrap.php';

// now we process
$app->process();