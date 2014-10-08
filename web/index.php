<?php

require_once __DIR__.'/../bootstrap.php'; 

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

$app->get('/', function() use($app) {
  $files = glob(__DIR__.'/../posts/*');
  return new Response('<h1>Begemot</h1><br>'.join('<br>', $files));
});

$app->match('/mandrill_hook_endpoint', function(Request $request) use($app) {
  if ($request->getMethod() == 'HEAD') {
    return new Response('ok');
  }

  $data = $request->get('mandrill_events');
  if (!$data)
  {
    return new Response('no data');
  }

  $filename = date('Y-m-d_H:i:s').'.json';
  file_put_contents(__DIR__.'/../requests/'.$filename, $data);

  parseData($data);

  return new Response('ok');
})->method('POST|HEAD'); 

$app->run();