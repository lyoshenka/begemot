<?php 

ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);

require_once __DIR__.'/../vendor/autoload.php'; 

require_once __DIR__.'/config.php';
require_once __DIR__.'/app.php';

$app = new MyApp(); 
$app['debug'] = true;

require_once __DIR__.'/services.php';

function post($from, $subject, $text) {
  $filename = date('Y-m-d') . '-' . preg_replace('/[^a-z0-9]+/', '-', strtolower($subject)) . '.md';

  $committer = ['name' => 'Begemot', 'email' => $from];

  try 
  {
    $fileInfo = $app['github']->api('repo')->contents()->create('lyoshenka', 'begemot-test', '_posts/'.$filename, $text, 'post via begemot: '.$subject, 'gh-pages', $committer);
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

  $message = [
    'to' => [
      ['type' => 'to', 'email' => $from]
    ],
    'subject' => 'Post Received',
    'text' => "We got your post. Here's the text:\n\n$text",
    'from_email' => 'begemot@begemot.grin.io',
    'from_name' => 'Begemot'
  ];

  try 
  {
    $result = $app['mailer']->messages->send($message);
  }
  catch(Mandrill_Error $e) 
  {
    // Mandrill errors are thrown as exceptions
    echo 'A mandrill error occurred: ' . get_class($e) . ' - ' . $e->getMessage();
    // A mandrill error occurred: Mandrill_Unknown_Subaccount - No subaccount exists with the id 'customer-123'
    throw $e;
  }
}

require_once __DIR__.'/routes.php';