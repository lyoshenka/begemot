<?php 

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

initRoutes($app);
function initRoutes($app) {

  $app->match('/', function(Request $request) use($app) {
    if ($app['user']) // if logged in, go to app
    {
      return $app->redirect($app->path('app'));
    }

    if ($request->getMethod() == 'POST')
    {
      $email = trim($request->get('email'));
      $errors = $app['validator']->validateValue($email, [
        new Assert\NotBlank(['message' => 'Please enter your email.']),
        new Assert\Email(),
        new Assert\Callback(function($email, Symfony\Component\Validator\ExecutionContextInterface $context) use($app) {
          $q = $app['pdo']->prepare('SELECT count(*) as has_one FROM email e WHERE e.email = :email LIMIT 1');
          $q->bindValue(':email', $email);
          $q->execute();
          $result = $q->fetch(PDO::FETCH_ASSOC);
          if ($result && $result['has_one'])
          {
            $context->addViolationAt('email','An account already exists for this email. Please log in or use a different email address.',[],null);
          }
        })
      ]);
      if ($errors->count())
      {
        foreach($errors as $error)
        {
          $app['session']->getFlashBag()->add('form_error', $error);
        }
        return $app->redirect($app->path('home'));
      }

      $app['pdo']->beginTransaction();
      $app['pdo']->prepare('INSERT INTO user SET created_at = ?')->execute([date('Y-m-d H:i:s')]);
      $userId = $app['pdo']->lastInsertId();
      $app['pdo']->prepare('INSERT INTO email SET user_id = ?, email = ?, is_primary = 1')->execute([$userId, $email]);
      $app['pdo']->commit();

      $message = [
        'to' => [
          ['type' => 'to', 'email' => $email]
        ],
        'subject' => 'Welcome to Begemot',
        'html' => $app['twig']->render('emails/login.twig', [
          'text' => 'Hey there. Here\'s your login link.',
          'moretext' => 'This is where more text goes.',
          'linkurl' => $app->url('login_with_hash', ['hash' => 'abcd']),
          'linktext' => 'Login'
        ]),
        'from_email' => 'begemot@begemot.grin.io',
        'from_name' => 'Begemot',
        'track_clicks' => false
      ];

      $app['mailer']->messages->send($message);

      return $app->redirect($app->path('new_user'));
    }

    return $app['twig']->render('home.twig');
  })
  ->method('GET|POST|HEAD')
  ->bind('home');



  $app->get('/new', function() use($app) {
    return new Response(
      'Thanks for trying out Begemot. Check your email for your login link. To make your life easier, we\'re not going to ask you to memorize yet another password. 
      Instead, you log in by entering your email address and we send you a link that will log you in. Please don\' share your login links with anyone else, or they will be able to log in as you.');
  })
  ->bind('new_user');

  $app->match('/login', function(Request $request) use($app) {
    if ($request->getMethod() == 'POST')
    {

    }
    return $app['twig']->render('login.twig');
  })
  ->method('GET|POST')
  ->bind('login');

  $app->get('/login/{hash}', function($hash) use($app) {
    
  })
  ->bind('login_with_hash');

  $app->get('/app', function() use($app) {
    if (!$app['user']) // if logged in, go to app
    {
      return $app->redirect($app->path('home'));
    }

    return new Response('Begemot: ' . $app['user']['id']);
  })->bind('app');

  $app->get('/github_connect', function() use($app) {
    if (!$app['user']) // if logged in, go to app
    {
      return $app->redirect($app->path('home'));
    }



    return new Response('Begemot: ' . $app['user']['id']);
  })->bind('app');  

  $app->get('/github_connect', function() use($app) {
    if (!$app['user']) // if logged in, go to app
    {
      return $app->redirect($app->path('home'));
    }

    $state = sha1($app['session']->get('user_id').'P4tc9g6dGs'.time());
    $app['session']->set('github_state', $state);
    $app['session']->save(); // force save and close, just in case

    return $app->redirect('https://github.com/login/oauth/authorize?' . http_build_query([
      'client_id' => $app['github.app_client_id'],
      'scope' => 'user,public_repo,repo',
      'state' => $state
    ]));
  })
  ->bind('github_connect');

  $app->get('/github_connect_callback', function(Request $request) use($app) {
    if (!$app['user']) // if logged in, go to app
    {
      return $app->redirect($app->path('home'));
    }

    if (!$request->get('state') || $request->get('state') != $app['session']->get('github_state'))
    {
      throw new Exception('Given state does not match stored state.');
    }

    $response = GuzzleHttp\post('https://github.com/login/oauth/access_token', [
      'headers' => [
        'Accept' => 'application/json'
      ],
      'body' => [
        'client_id' => $app['github.app_client_id'],
        'client_secret' => $app['github.app_client_secret'],
        'code' => $request->get('code')
      ]
    ])->json();

    if (isset($response['error']))
    {
      // error from github
    }

    $grantedScopes = explode(',', $response['scope']);
    if (!in_array('repo', $grantedScopes))
    {
      // will not be able to read private repos
    }
    if (!in_array('public_repo', $grantedScopes))
    {
      // will not be able to read public repos
    }
    if (!in_array('user', $grantedScopes))
    {
      // not sure what happens here
    }

    $stmt = $app['pdo']->prepare('UPDATE user SET github_token = :token, github_token_score = :scope WHERE id = :id');
    $stmt->bindValue(':token', $response['access_token']);
    $stmt->bindValue(':scope', $response['scope']);
    $stmt->bindValue(':id', $app['user']['id']);
    $stmt->execute();

    return $app->redirect($app->path('app'));
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
  })
  ->method('POST|HEAD');   


  $app->error(function(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, $code) use ($app) {
    return $app['twig']->render('404.twig');
  });
}
