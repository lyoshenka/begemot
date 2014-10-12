<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

initRoutes($app);
function initRoutes($app) {

  require_once __DIR__.'/routes/github.php';

  $app->match('/', function(Request $request) use($app) {
    if ($app['user']) // if logged in, do app
    {
      return $app->redirect($app->path('app'));
    }

    $errors = [];

    if ($request->getMethod() == 'POST')
    {
      if ($request->get('submit') == 'join')
      {

        $email = trim($request->get('email'));
        $errors = $app['validator']->validateValue($email, [
          new Assert\NotBlank(['message' => 'Please enter your email.']),
          new Assert\Email(),
          new Assert\Callback(function($email, Symfony\Component\Validator\ExecutionContextInterface $context) use($app) {
            $result = $app['pdo']->fetchOne('SELECT count(*) as has_one FROM email e WHERE e.email = ? LIMIT 1', $email);
            if ($result && $result['has_one'])
            {
              $context->addViolationAt('email','An account already exists for this email. Please log in or use a different email address.');
            }
          })
        ]);

        if (!$errors->count())
        {

          $app['pdo']->beginTransaction();
          $app['pdo']->execute('INSERT INTO user SET created_at = ?', date('Y-m-d H:i:s'));
          $userId = $app['pdo']->lastInsertId();
          $app['pdo']->execute('INSERT INTO email SET user_id = ?, email = ?, is_primary = 1', [$userId, $email]);
          $app['pdo']->commit();

          $app['log_event']('user.create', null, $userId);

          $hash = $app['create_onetime_login']($userId);
          $app['mailer']->sendJoinEmail($email, $hash);

          return new Response(
            'Thanks for trying out Begemot. Check your email for your login link. To make your life easier, we\'re not going to ask you to memorize yet another password.
            Instead, you log in by entering your email address and we send you a link that will log you in. Please don\' share your login links with anyone else, or they will be able to log in as you.');
        }
      }
      elseif($request->get('submit') == 'login')
      {
        $email = trim($request->get('email'));
        $errors = $app['validator']->validateValue($email, [
          new Assert\NotBlank(['message' => 'Please enter your email.']),
          new Assert\Email()
        ]);

        $user = null;

        if (!$errors->count())
        {
          $user = $app['pdo']->fetchOne('SELECT u.* FROM user u INNER JOIN email e ON u.id = e.user_id AND e.email = ? LIMIT 1', $email);
          if (!$user)
          {
            $errors->add(new Symfony\Component\Validator\ConstraintViolation(
              'This email address is not in our system. Please double-check the address or create a new account.',
              '', [], '', '', $email
            ));
          }
        }

        if (!$errors->count())
        {
          $hash = $app['create_onetime_login']($user['id']);
          $app['mailer']->sendLoginEmail($email, $hash);
          $loginEmailSent = true;
          return new Response('Login email sent');
//          return $app['twig']->render('login.twig', ['errors' => $errors, 'loginEmailSent' => $loginEmailSent]);
        }
      }
    }

    return $app['twig']->render('home.twig', ['errors' => $errors]);
  })
  ->method('GET|POST|HEAD')
  ->bind('home');



  $app->get('/login/{hash}', function($hash) use($app) {
    $user = $app['pdo']->fetchOne(
      'SELECT u.* FROM user u INNER JOIN onetime_login o ON u.id = o.user_id AND o.hash = ? AND o.created_at > SUBTIME(NOW(), "00:30:00") LIMIT 1', $hash
    );

    if ($user)
    {
      $app['session']->set('user_id', $user['id']);
      $app['log_event']('user.login', null, $user['id']);
      $app['session']->save();
      return $app->redirect($app->path('app'));
    }

    $app['session']->set('user_id', null); // if they were logged in as someone else, log them out just in case

    return new Response('This login link is invalid or it has expired. Please <a href="' . $app->path('home') . '">click here</a> to try logging in again.');

  })
  ->bind('login_with_hash');



  $app->get('/logout', function() use($app) {
    $app['session']->set('user_id', null);
    $app['session']->save();
    return $app->redirect($app->path('home'));
  })
  ->bind('logout');



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

    $events = $app['pdo']->fetchAssoc('SELECT * FROM event WHERE user_id = ? ORDER BY created_at DESC LIMIT 10', $app['user']['id']);

    return $app['twig']->render('app.twig', ['user' => $app['user'], 'events' => $events]);
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
      $senderEmail = $event['msg']['from_email'];

      $app->log('got email from ' . $senderEmail);

      $stmt = $app['pdo']->fetchOne('SELECT u.* FROM user u INNER JOIN email e ON u.id = e.user_id AND e.email = ?', $senderEmail);

      if (!$user)
      {
        continue; // user not found. we could notify them, but dont wanna deal with spam
      }

      $postTitle = $event['msg']['subject'];
      $postText = trim($event['msg']['text']);

      $filename = date('Y-m-d') . '-' . rtrim(preg_replace('/[^a-z0-9]+/', '-', strtolower($postTitle)), '-') . '.md';

      list($githubUsername,$repo) = explode('/', $user['github_repo']);
      $committer = ['name' => 'Begemot', 'email' => $senderEmail];

      try
      {
        $app['github']->authenticate($user['github_token'], null, Github\Client::AUTH_HTTP_TOKEN);
        $fileInfo = $app['github']->api('repo')->contents()->create(
          $githubUsername, $repo, $user['posts_path'].'/'.$filename, $postText, 'post via begemot: '.$postTitle, $user['github_branch'], $committer
        );
        $app->log('Successfully created post');
        $app['log_event']('post.publish', $postTitle, $user['id']);
        $app['mailer']->sendPublishSuccessEmail($senderEmail, $postTitle);
        $app->log('Sent publish success email');
      }
      catch (Github\Exception\RuntimeException $e)
      {
        if (stripos($e->getMessage(), 'Missing required keys "sha" in object') !== false)
        {
          $app->log('Post exists for filename "' . $filename . '"');
          $app['log_event']('post.error', $postTitle, $user['id']);
          $app['mailer']->sendPublishErrorEmail($senderEmail, $postTitle, 'A post with the same title already exists for today');
          $app->log('Sent publish error email');
        }
        else
        {
          $app->log('Error creating file on github: ' . $e);
          $app['log_event']('post.error', $postTitle, $user['id']);
          $app['mailer']->sendPublishErrorEmail($senderEmail, $postTitle,
            'Got an error from GitHub: "' . $e->getMessage . '". If this doesn\'t help clear things up, please forward this email to ' . $app['config.support_email']
          );
          $app->log('Sent publish error email');
        }
      }
    }

    return new Response('ok');
  })
  ->method('POST|HEAD');



  $app->error(function(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, $code) use ($app) {
    return $app['twig']->render('404.twig');
  });
}
