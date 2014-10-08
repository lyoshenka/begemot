<?php

require_once __DIR__.'/../vendor/autoload.php'; 

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

$app = new Silex\Application(); 
$app['debug'] = true;

$app->get('/', function() use($app) {
  return new Response('Begemot');
});

function post($from, $subject, $text) {
  $filename = date('Y-m-d') . '-' . preg_replace('/[^a-z0-9]+/', '-', strtolower($subject) . '.md');
  file_put_contents(__DIR__.'/../posts/'.$filename, $text);
}

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

  $events = json_decode($data, true);
  foreach($events as $event)
  {
    post($event['from_email'], $event['subject'], $event['text']);
  }

  return new Response('ok');
})->method('POST|HEAD'); 

$app->run();