<?php 

ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);

require_once __DIR__.'/../vendor/autoload.php'; 

require_once __DIR__.'/config.php';
require_once __DIR__.'/app.php';

$app = new MyApp(); 
$app['debug'] = true;

require_once __DIR__.'/services.php';

require_once __DIR__.'/routes.php';