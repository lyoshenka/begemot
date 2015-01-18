<?php

ini_set('error_reporting', E_ALL|E_STRICT);
ini_set('display_errors', 1);
date_default_timezone_set('UTC');

require_once __DIR__.'/../vendor/autoload.php';

require_once __DIR__.'/config.php';
require_once __DIR__.'/app.php';

$app = new MyApp();
//$app['debug'] = true;


// Initialize Services
require_once __DIR__.'/services.php';


// Mount Routes
require_once __DIR__.'/routes/main.php';
require_once __DIR__.'/routes/github.php';

// If no route matches, 404
$app->error(function(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, $code) use ($app) {
  return $app['twig']->render('404.twig');
});