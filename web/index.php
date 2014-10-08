<?php

require_once __DIR__.'/../bootstrap.php'; 

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

$app->get('/', function() use($app) {
  return new Response('Begemot');
});

$app->match('/mandrill_hook_endpoint', function(Request $request) use($app) {
  if ($request->getMethod() == 'HEAD') {
    return new Response('ok');
  }

  $data = $request->get('mandrill_events');
  if (!$data)
  {
    return new Response('mandrill_events is empty', 422);
  }

  parseData($data);

  return new Response('ok');
})->method('POST|HEAD'); 

$app->run();