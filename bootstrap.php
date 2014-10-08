<?php 

require_once __DIR__.'/vendor/autoload.php'; 

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

$app = new Silex\Application(); 
$app['debug'] = true;

function parseData($data) {
  $events = json_decode($data, true);
  foreach($events as $event)
  {
    post($event['msg']['from_email'], $event['msg']['subject'], $event['msg']['text']);
  }
}

function post($from, $subject, $text) {
  $filename = date('Y-m-d') . '-' . preg_replace('/[^a-z0-9]+/', '-', strtolower($subject)) . '.md';
  file_put_contents(__DIR__.'/posts/'.$filename, $text);

  $client = new Github\Client();
  $client->authenticate('e72e2fa8f8e8640ead4dbf35a1bac1bb99cd3652', null, Github\Client::AUTH_HTTP_TOKEN);
  $committer = ['name' => 'Begemot', 'email' => $from];

  try 
  {
    $fileInfo = $client->api('repo')->contents()->create('lyoshenka', 'begemot-test', '_posts/'.$filename, $text, 'post via begemot: '.$subject, 'gh-pages', $committer);
  }
  catch (Github\Exception\RuntimeException $e)
  {
    if (stripos($e->getMessage(), 'Missing required keys "sha" in object') !== false)
    {
      // file with that name already exists, so github thinks we're trying to edit it
    }
    else
    {
      throw $e;
    }
  }

  respond($from, $text);
}

function respond($address, $text) {
  $mandrill = new Mandrill('1U8r64YW1GYJ2aZmNrcGJA'); // TODO: change this before going live
  $message = [
    'to' => [
      ['type' => 'to', 'email' => $address]
    ],
    'subject' => 'Post Received',
    'text' => "We got your post. Here's the text:\n\n$text",
    'from_email' => 'begemot@begemot.grin.io',
    'from_name' => 'Begemot'
  ];

  try {
    $result = $mandrill->messages->send($message);
  }
  catch(Mandrill_Error $e) {
    // Mandrill errors are thrown as exceptions
    echo 'A mandrill error occurred: ' . get_class($e) . ' - ' . $e->getMessage();
    // A mandrill error occurred: Mandrill_Unknown_Subaccount - No subaccount exists with the id 'customer-123'
    throw $e;
  }
}
