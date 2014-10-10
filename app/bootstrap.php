<?php

ini_set('error_reporting', E_ALL|E_STRICT);
ini_set('display_errors', 1);
date_default_timezone_set('UTC');

require_once __DIR__.'/../vendor/autoload.php';

require_once __DIR__.'/config.php';
require_once __DIR__.'/app.php';

$app = new MyApp();
$app['debug'] = true;

require_once __DIR__.'/services.php';

require_once __DIR__.'/routes.php';