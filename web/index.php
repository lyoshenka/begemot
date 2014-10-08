<?php

require_once __DIR__.'/../vendor/autoload.php'; 

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

$app = new Silex\Application(); 
$app['debug'] = true;

$app->get('/', function() use($app) {
  return new Response('Begemot');
});

$app->match('/mandrill_hook_endpoint', function(Request $request) use($app) { 
  if ($request->getMethod() == 'HEAD') {
    return new Response('ok');
  }

  $data = $request->get('mandrill_events');
  $filename = date('Y-m-d_H:i:s').'.json';
  file_put_contents(__DIR__.'/../requests/'.$filename, $data);
  return new Response('ok');
})->method('POST|HEAD'); 

$app->run();