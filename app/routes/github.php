<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;


setupGithubRoutes($app);
function setupGithubRoutes($app) {
  $routes = $app['controllers_factory'];


  $routes->match('/repo', function(Request $request) use($app) {
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




  $routes->match('/branch', function(Request $request) use($app) {
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
    $branchNames = array_map(function($b) { return $b['name']; }, $branches);

    $branch = $app['user']['github_branch'];
    $errors = null;

    if ($request->isMethod('POST'))
    {
      $branch = $request->get('branch');

      $errors = $app['validator']->validateValue($branch, [
        new Assert\NotBlank(['message' => 'Please choose a branch']),
        new Assert\Choice([
          'choices' => $branchNames,
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

    usort($branchNames, function($a, $b) {
      if ($a == 'gh-pages') return -1;
      if ($b == 'gh-pages') return 1;
      if ($a == 'master') return -1;
      if ($b == 'master') return 1;
      return strnatcasecmp($a, $b);
    });

    return $app['twig']->render('github_select_branch.twig', ['branches' => $branchNames, 'selectedBranch' => $branch, 'errors' => $errors]);
  })
  ->method('GET|POST')
  ->bind('github_select_branch');



  $routes->match('/path', function(Request $request) use($app) {
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



  $routes->get('/connect', function() use($app) {
    if ($app['user'])
    {
      return $app->redirect($app->path('app'));
    }

    $state = str_shuffle(sha1(microtime(true)));
    $app['session']->set('github_state', $state);
    $app['session']->save(); // force save and close, just in case

    return $app->redirect('https://github.com/login/oauth/authorize?' . http_build_query([
      'client_id' => $app['github.app_client_id'],
      'scope' => 'user,public_repo', // add 'repo' for private repos
      'state' => $state
    ]));
  })
  ->bind('github_connect');



  $routes->get('/connect_callback', function(Request $request) use($app) {
    if ($app['user']) // if logged in, go to app
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
      $msg = 'Error from GitHub: ' . $response['error'];
      $app['monolog']->addError($msg);
      $app->addFlash('error', $msg . '. Please contact ' . $app['config.support_email'] . ' for assistance.');
      return $app->redirect($app->path('home'));
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
      $msg = 'User scope required';
      $app['monolog']->addError($msg);
      $app->addFlash('error', $msg . '. Please contact ' . $app['config.support_email'] . ' for assistance.');
      return $app->redirect($app->path('home'));
    }


    $githubUserInfo = GuzzleHttp\get('https://api.github.com/user', [
      'headers' => [
        'Accept' => 'application/json',
        'Authorization' => 'token ' . $response['access_token'],
      ],
    ])->json();

    if (isset($githubUserInfo['message']))
    {
      $msg = 'Error from GitHub: ' . $response['message'];
      $app['monolog']->addError($msg);
      $app->addFlash('error', $msg . '. Please contact ' . $app['config.support_email'] . ' for assistance.');
      return $app->redirect($app->path('home'));
    }

    $user = $app['pdo']->fetchOne('SELECT * FROM user WHERE github_user_id = :id', [':id' => $githubUserInfo['id']]);
    $existingEmails = [];

    if (!$user)
    {
      $app['pdo']->execute('INSERT INTO user SET created_at = :date, github_user_id = :id, github_token = :token, github_token_scope = :scope', [
        ':id' => $githubUserInfo['id'],
        ':date' => date('Y-m-d H:i:s'),
        ':token' => $response['access_token'],
        ':scope' => $response['scope']
      ]);
      $userId = $app['pdo']->lastInsertId();
      $app['log_event']('user.create', null, $userId);
      $app['session']->set('just_created_new_account', true);
    }
    else
    {
      $userId = $user['id'];
      $existingEmails = array_map(
        function($item) { return $item['email']; },
        (array)$app['pdo']->fetchAssoc('SELECT email FROM email WHERE user_id = ?', $userId)
      );
    }

    $emailInfo = GuzzleHttp\get('https://api.github.com/user/emails', [
      'headers' => [
        'Accept' => 'application/json',
        'Authorization' => 'token ' . $response['access_token']
      ]
    ])->json();


    $githubEmails = array_filter(array_map(function($item) { return $item['verified'] ? $item['email'] : null; }, (array)$emailInfo));
    $githubPrimaryEmail = array_values(array_filter(array_map(function($item) { return $item['primary'] ? $item['email'] : null; }, (array)$emailInfo)))[0];
    $newEmails = array_diff($githubEmails, $existingEmails);
    $emailsToRemove = array_diff($existingEmails, $githubEmails);

    if (!in_array($githubPrimaryEmail, $githubEmails))
    {
      // that means primary address is not verified. gotta be safe.
      $msg = 'Your primary address is not verified. Please verify your address on GitHub, then log in again.';
      $app['monolog']->addError($msg);
      $app->addFlash('error', $msg . ' Please contact ' . $app['config.support_email'] . ' for assistance.');
      return $app->redirect($app->path('home'));
    }

    $newEmailsAlreadyInDb = [];
    if ($newEmails)
    {
      $inQuery = implode(',', array_fill(0, count($newEmails), '?'));

      $stmt = $app['pdo']->prepare('SELECT email FROM email WHERE user_id != ? AND email IN(' . $inQuery . ')');
      $stmt->bindValue(1, $userId);
      $index = 2;
      foreach($newEmails as $email)
      {
        $stmt->bindValue($index, $email);
        $index++;
      }
      $stmt->execute();

      $newEmailsAlreadyInDb = array_map(
        function($item) { return $item['email']; },
        (array)$stmt->fetchAll(PDO::FETCH_ASSOC)
      );
    }

    foreach($newEmails as $email)
    {
      if (in_array($email, $newEmailsAlreadyInDb))
      {
        continue;
      }

      $app['pdo']->execute('INSERT INTO email SET email = :email, user_id = :userId', [
        ':email' => $email,
        ':userId' => $userId
      ]);
    }

    foreach($emailsToRemove as $email)
    {
      $app['pdo']->execute('DELETE email WHERE email = :email AND user_id = :userId', [
        ':email' => $email,
        ':userId' => $userId
      ]);
    }

    $app['pdo']->execute('UPDATE email SET is_primary = IF(email = :primary,1,0) WHERE user_id = :userId', [
      ':primary' => $githubPrimaryEmail,
      ':userId' => $userId
    ]);


    $app['session']->set('user_id', $userId);
    $app['session']->save();
    return $app->redirect($app->path('app'));
  });



  $app->mount('/github', $routes);
}

