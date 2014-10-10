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

      $inliner = new Northys\CSSInliner\CSSInliner();
      $inliner->addCSS(__DIR__ . '/views/emails/email_styles.css');

      $message = [
        'to' => [
          ['type' => 'to', 'email' => $email]
        ],
        'subject' => 'Welcome to Begemot',
        'html' => $inliner->render($app['twig']->render('emails/login.twig', [
          'url' => $app->url('login_with_hash', ['hash' => 'abcd']),
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
    if ($request->getMethod() == 'POST')
    {

    }
    return $app['twig']->render('login.twig');
  })
  ->method('GET|POST')
  ->bind('login');



  $app->get('/login/{hash}', function($hash) use($app) {
    if ($hash == 'abcd')
    {
      $stmt = $app['pdo']->prepare('SELECT id FROM user ORDER BY id ASC LIMIT 1');
      $stmt->execute();
      $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
      if ($user)
      {
        $app['session']->set('user_id', $user['id']);
      }
    }
    return $app->redirect($app->path('app'));
  })
  ->bind('login_with_hash');



  $app->get('/app', function() use($app) {


    var_export($app['github']->api('repo')->contents()->create(
      'lyoshenka', 'begemot-test', '_posts/2014-10-09-oyaursntyuwfs.md', 'oayrustnnoupufupyf  ', 'post via begemot: oyaursntyuwf', 'gh-pages', array (     'name' => 'Begemot',     'email' => 'lyoshenka@gmail.com',   )
    ));

    die();

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
      $subReq = Request::create($app->path('github_select_repo'), 'GET');
      return $app->handle($subReq, Symfony\Component\HttpKernel\HttpKernelInterface::SUB_REQUEST);
    }

    if (!$app['user']['github_branch'])
    {
      $subReq = Request::create($app->path('github_select_branch'), 'GET');
      return $app->handle($subReq, Symfony\Component\HttpKernel\HttpKernelInterface::SUB_REQUEST);
    }

    if (!$app['user']['posts_path'])
    {
      $subReq = Request::create($app->path('github_select_path'), 'GET');
      return $app->handle($subReq, Symfony\Component\HttpKernel\HttpKernelInterface::SUB_REQUEST);
    }

    return $app['twig']->render('app.twig', ['user' => $app['user']]);
  })->bind('app');  



  $app->match('/github_select_repo', function(Request $request) use($app) {
    if (!$app['user'])
    {
      return $app->redirect($app->path('home'));
    }

    $repos = $app['github']->api('me')->repositories('all', 'updated', 'desc');
    $repo = $app['user']['github_repo'];
    $errors = null;

    if ($request->isMethod('POST'))
    {
      $repo = $request->get('repo');

      $errors = $app['validator']->validateValue($repo, [
        new Assert\NotBlank(['message' => 'Please choose a repository']),
        new Assert\Choice([
          'choices' => array_map(function($r) { return $r['full_name']; }, $repos),
          'message' => "Invalid repository"
        ])
      ]);

      if (!$errors->count())
      {
        $stmt = $app['pdo']->prepare('UPDATE user SET github_repo = :repo WHERE id = :id');
        $stmt->bindValue(':repo', $repo);
        $stmt->bindValue(':id', $app['user']['id']);
        $stmt->execute();
        return $app->redirect($app->path('github_select_branch'));
      }
    }

    return $app['twig']->render('github_select_repo.twig', ['repos' => $repos, 'selectedRepo' => $repo, 'errors' => $errors]);
  })
  ->method('GET|POST')
  ->bind('github_select_repo');



  $app->match('/github_select_branch', function(Request $request) use($app) {
    if (!$app['user'])
    {
      return $app->redirect($app->path('home'));
    }
    if (!$app['user']['github_repo'])
    {
      return $app->redirect($app->path('github_select_repo'));
    }

    list($githubUsername,$repo) = explode('/', $app['user']['github_repo']);
    $branches = $app['github']->api('repo')->branches($githubUsername, $repo);

    $branch = $app['user']['github_branch'];
    $errors = null;

    if ($request->isMethod('POST'))
    {
      $branch = $request->get('branch');

      $errors = $app['validator']->validateValue($branch, [
        new Assert\NotBlank(['message' => 'Please choose a branch']),
        new Assert\Choice([
          'choices' => array_map(function($b) { return $b['name']; }, $branches),
          'message' => "Invalid branch"
        ])
      ]);

      if (!$errors->count())
      {
        $stmt = $app['pdo']->prepare('UPDATE user SET github_branch = :branch WHERE id = :id');
        $stmt->bindValue(':branch', $branch);
        $stmt->bindValue(':id', $app['user']['id']);
        $stmt->execute();
        return $app->redirect($app->path('github_select_path'));
      }
    }

    return $app['twig']->render('github_select_branch.twig', ['branches' => $branches, 'selectedBranch' => $branch, 'errors' => $errors]);
  })
  ->method('GET|POST')
  ->bind('github_select_branch');



  $app->match('/github_select_path', function(Request $request) use($app) {
    if (!$app['user'])
    {
      return $app->redirect($app->path('home'));
    }

    $path = $app['user']['posts_path'] ?: '_posts';
    $errors = null;

    if ($request->isMethod('POST'))
    {
      $path = trim($request->get('path'), '/');

      $errors = $app['validator']->validateValue($path, [
        new Assert\NotBlank(['message' => 'Please enter a directory']),
        new Assert\Callback(function($email, Symfony\Component\Validator\ExecutionContextInterface $context) use($app, $path) {
          list($githubUsername,$repo) = explode('/', $app['user']['github_repo']);
          $found = true;
          try
          {
            $dir = $app['github']->api('repo')->contents()->show($githubUsername, $repo, $path, $app['user']['github_branch']);
          }
          catch (RuntimeException $e)
          {
            if ($e->getMessage() == 'Not Found')
            {
              $found = false;
            }
            else
            {
              throw $e;
            }
          }
          if (!$found || !$dir || !is_array($dir))
          {
            $context->addViolationAt('','This directory doesn\'t exist. Please create it first, or choose another directory.');
          }
        })
      ]);

      if (!$errors->count())
      {
        $stmt = $app['pdo']->prepare('UPDATE user SET posts_path = :path WHERE id = :id');
        $stmt->bindValue(':path', $path);
        $stmt->bindValue(':id', $app['user']['id']);
        $stmt->execute();
        return $app->redirect($app->path('app'));
      }
    }

    return $app['twig']->render('github_select_path.twig', ['selectedPath' => $path, 'errors' => $errors]);
  })
  ->method('GET|POST')
  ->bind('github_select_path');
  


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

    $stmt = $app['pdo']->prepare('UPDATE user SET github_token = :token, github_token_scope = :scope WHERE id = :id');
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

      $inliner = new Northys\CSSInliner\CSSInliner();
      $inliner->addCSS(__DIR__ . '/views/emails/email_styles.css');

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
        $message['html'] = $inliner->render(
          $app['twig']->render('emails/post_received.twig', ['title' => $subject])
        );

      }
      catch (Github\Exception\RuntimeException $e)
      {
        if (stripos($e->getMessage(), 'Missing required keys "sha" in object') !== false)
        {
          $app->log('Post exists for filename "' . $filename . '"');
          $message['subject'] = 'Post Error';
          $message['html'] = $inliner->render(
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
          $message['html'] = $inliner->render(
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
