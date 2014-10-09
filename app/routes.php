<?php 

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

initRoutes($app);
function initRoutes($app) {

  $app->get('/', function() use($app) {
    $email = $app['session']->get('email');
    if (!$email) 
    {
      // login screen
      return new Response('Begemot');
    }
    
  })->bind('home');

  $app->post('/', function(Request $request) use($app) {
    $email = $request->get('email');
    $user = $app['pdo']->query('SELECT * FROM user u INNER JOIN email e ON e.user_id = u.id AND e.email = ? LIMIT 1', [$email]);
    if ($user) 
    {
      // send them a login email
      return $app->redirect('/login_email_sent');
    }
    else
    {
      $app['pdo']->exec('INSERT INTO user SET created_at = ?')->execute([date('Y-m-d H:i:s')]);
      $userId = $app['pdo']->lastInsertId();
      $app['pdo']->prepare('INSERT INTO email SET user_id = ?, email = ?, is_primary = 1')->execute([$userId, $email]);
      $app['session']->set('email', $email);

      // send them a login email

      return $app->redirect($app->path('home'));
    }
  });

  $app->match('/mandrill_hook_endpoint', function(Request $request) use($app) {
    if ($request->getMethod() == 'HEAD') 
    {
      return new Response('ok');
    }

    $data = $request->get('mandrill_events');
    if (!$data)
    {
      return new Response('mandrill_events is empty', 422);
    }

    $events = json_decode($data, true);
    foreach($events as $event)
    {
      post($event['msg']['from_email'], $event['msg']['subject'], $event['msg']['text']);
    }

    return new Response('ok');
  })->method('POST|HEAD');   
}
