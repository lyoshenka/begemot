<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

initRoutes($app);
function initRoutes($app) {

  require_once __DIR__.'/routes/github.php';

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
            $context->addViolationAt('email','An account already exists for this email. Please log in or use a different email address.');
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
        'html' => $app['css_inliner']->render($app['twig']->render('emails/login.twig', [
          'url' => $app->url('login_with_hash', ['hash' => 'abcd']),
          'newAccount' => true
        ])),
        'from_email' => $app['config.system_email'],
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
    if ($app['user']) // if logged in, go to app
    {
      return $app->redirect($app->path('home'));
    }
    return new Response(
      'Thanks for trying out Begemot. Check your email for your login link. To make your life easier, we\'re not going to ask you to memorize yet another password.
      Instead, you log in by entering your email address and we send you a link that will log you in. Please don\' share your login links with anyone else, or they will be able to log in as you.');
  })
  ->bind('new_user');



  $app->match('/login', function(Request $request) use($app) {
    if ($app['user']) // if logged in, go to app
    {
      return $app->redirect($app->path('app'));
    }

    $errors = null;
    $loginEmailSent = false;

    if ($request->getMethod() == 'POST')
    {
      $email = trim($request->get('login_email'));
      $errors = $app['validator']->validateValue($email, [
        new Assert\NotBlank(['message' => 'Please enter your email.']),
        new Assert\Email()
      ]);

      $user = null;

      if (!$errors->count())
      {
        $stmt = $app['pdo']->prepare('SELECT u.* FROM user u INNER JOIN email e ON u.id = e.user_id AND e.email = :email LIMIT 1');
        $stmt->bindValue(':email', $email);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user)
        {
          $errors->add(new Symfony\Component\Validator\ConstraintViolation(
            'This email address is not in our system. Please double-check the address or <a href="' . $app->path('/') . '">create a new account</a>.',
            '', [], '', '', $email
          ));
        }
      }

      if (!$errors->count())
      {
        $hash = sha1(time().'mumb0jum7bo');
        $q = $app['pdo']->prepare('INSERT INTO onetime_login SET hash = :hash, user_id = :userId, created_at = NOW()');
        $q->bindValue(':hash', $hash);
        $q->bindValue(':userId', $user['id']);
        $q->execute();

        $message = [
          'to' => [
            ['type' => 'to', 'email' => $email]
          ],
          'subject' => 'Begemot Login',
          'html' => $app['css_inliner']->render($app['twig']->render('emails/login.twig', [
            'url' => $app->url('login_with_hash', ['hash' => $hash]),
          ])),
          'from_email' => $app['config.system_email'],
          'from_name' => 'Begemot',
          'track_clicks' => false
        ];

        $app['mailer']->messages->send($message);

        $loginEmailSent = true;
      }
    }

    return $app['twig']->render('login.twig', ['errors' => $errors, 'loginEmailSent' => $loginEmailSent]);
  })
  ->method('GET|POST')
  ->bind('login');



  $app->get('/login/{hash}', function($hash) use($app) {
    $stmt = $app['pdo']->prepare('SELECT u.* FROM user u INNER JOIN onetime_login o ON u.id = o.user_id AND o.hash = :hash AND o.created_at > SUBTIME(NOW(), "00:30:00") LIMIT 1');
    $stmt->bindValue(':hash', $hash);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user)
    {
      $app['session']->set('user_id', $user['id']);
      return $app->redirect($app->path('app'));
    }

    return new Response('This login link is invalid or it has expired. Please <a href="' . $app->path('login') . '">click here</a> to try logging in again.');

  })
  ->bind('login_with_hash');



  $app->get('/app', function() use($app) {
    if (!$app['user']) // if logged in, go to app
    {
      return $app->redirect($app->path('home'));
    }

    if (!$app['user']['github_token'])
    {
      return $app->redirect($app->path('github_connect'));
    }

    if (!$app['user']['github_repo'])
    {
      return $app->forward($app->path('github_select_repo'));
    }

    if (!$app['user']['github_branch'])
    {
      return $app->forward($app->path('github_select_branch'));
    }

    if (!$app['user']['posts_path'])
    {
      return $app->forward($app->path('github_select_path'));
    }

    return $app['twig']->render('app.twig', ['user' => $app['user']]);
  })->bind('app');





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
      $from = $event['msg']['from_email'];

      $app->log('got email from ' . $from);

      $stmt = $app['pdo']->prepare('SELECT u.* FROM user u INNER JOIN email e ON u.id = e.user_id AND e.email = :email');
      $stmt->bindValue(':email', $from);
      $stmt->execute();
      $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

      if (!$user)
      {
        continue; // user not found. we could notify them, but dont wanna deal with spam
      }

      $subject = $event['msg']['subject'];
      $text = $event['msg']['text'];

      $filename = date('Y-m-d') . '-' . rtrim(preg_replace('/[^a-z0-9]+/', '-', strtolower($subject)), '-') . '.md';

      list($githubUsername,$repo) = explode('/', $user['github_repo']);
      $committer = ['name' => 'Begemot', 'email' => $from];

      $message = [
        'to' => [
          ['type' => 'to', 'email' => $from]
        ],
        'from_email' => $app['config.system_email'],
        'from_name' => 'Begemot',
        'track_clicks' => false
      ];


      try
      {
        $app['github']->authenticate($user['github_token'], null, Github\Client::AUTH_HTTP_TOKEN);
        $fileInfo = $app['github']->api('repo')->contents()->create(
          $githubUsername, $repo, $user['posts_path'].'/'.$filename, trim($text), 'post via begemot: '.$subject, $user['github_branch'], $committer
        );
        $app->log('Successfully created post');
        $message['subject'] = 'Post Published';
        $message['html'] = $app['css_inliner']->render(
          $app['twig']->render('emails/post_received.twig', ['title' => $subject])
        );

      }
      catch (Github\Exception\RuntimeException $e)
      {
        if (stripos($e->getMessage(), 'Missing required keys "sha" in object') !== false)
        {
          $app->log('Post exists for filename "' . $filename . '"');
          $message['subject'] = 'Post Error';
          $message['html'] = $app['css_inliner']->render(
            $app['twig']->render('emails/post_error.twig', [
              'title' => $subject,
              'text' => 'A post with the same title already exists for today'
            ])
          );
        }
        else
        {
          $app->log('Error creating file on github: ' . $e);
          $message['subject'] = 'Post Error';
          $message['html'] = $app['css_inliner']->render(
            $app['twig']->render('emails/post_error.twig', [
              'title' => $subject,
              'text' => 'Got an error from GitHub: "' . $e->getMessage . '". If this doesn\'t help clear things up, please forward this email to ' . $app['config.support_email']
            ])
          );
        }
      }


      try
      {
        $result = $app['mailer']->messages->send($message);
        $app->log('Sent publish email');
      }
      catch(Mandrill_Error $e)
      {
        $app->log('Error sending email: ' . $e);
        // Mandrill errors are thrown as exceptions
        echo 'A mandrill error occurred: ' . get_class($e) . ' - ' . $e->getMessage();
        // A mandrill error occurred: Mandrill_Unknown_Subaccount - No subaccount exists with the id 'customer-123'
        throw $e;
      }
    }

    return new Response('ok');
  })
  ->method('POST|HEAD');



  $app->error(function(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, $code) use ($app) {
    return $app['twig']->render('404.twig');
  });
}
