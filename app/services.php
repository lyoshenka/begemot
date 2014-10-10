<?php

initServices($app);
function initServices($app) {

  /*------------------------------*\
              CONFIG
  \*------------------------------*/
  $app['config.system_email'] = 'begemot@begemot.grin.io';
  $app['config.post_email'] = 'post@begemot.grin.io';
  $app['config.support_email'] = 'grin@grin.io';


  /*------------------------------*\
              ROUTING
  \*------------------------------*/
  $app->register(new Silex\Provider\UrlGeneratorServiceProvider());


  /*------------------------------*\
              VALIDATION 
  \*------------------------------*/
  $app->register(new Silex\Provider\ValidatorServiceProvider());


  /*------------------------------*\
              DATABASE
  \*------------------------------*/

  $app['pdo.db'] = MYSQL_DB;
  $app['pdo.host'] = MYSQL_HOST;
  $app['pdo.user'] = MYSQL_USER;
  $app['pdo.pass'] = MYSQL_PASS;

  $app['pdo'] = $app->share(function ($app) {
    return new PDO("mysql:host={$app['pdo.host']};dbname={$app['pdo.db']};charset=UTF8", $app['pdo.user'], $app['pdo.pass'], [
      PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'UTF8'",
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
  });


  /*------------------------------*\
              SESSION
  \*------------------------------*/
  $app->register(new Silex\Provider\SessionServiceProvider());

  $app['session.storage.options'] = [
    'name' => 'bgid',
    'cookie_httponly' => true,
    'cookie_lifetime' => 31536000, // one year
  ];
  $app['session.db_options'] = [
    'db_table'    => 'session',
    'db_id_col'   => 'id',
    'db_data_col' => 'value',
    'db_time_col' => 'time',
  ];

  $app['session.storage.handler'] = $app->share(function ($app) {
    return new Symfony\Component\HttpFoundation\Session\Storage\Handler\PdoSessionHandler(
      $app['pdo'], $app['session.db_options']
    );
  });


  /*------------------------------*\
              GITHUB
  \*------------------------------*/
  $app['github.app_client_id'] = GITHUB_APP_CLIENT_ID;
  $app['github.app_client_secret'] = GITHUB_APP_CLIENT_SECRET;
  $app['github'] = $app->share(function($app) {
    $gh = new Github\Client();
    if ($app['user'] && $app['user']['github_token'])
    {
      $gh->authenticate($app['user']['github_token'], null, Github\Client::AUTH_HTTP_TOKEN);
      $app->log('Authenticated with GitHub');
    }
    return $gh;
  });


  /*------------------------------*\
              MAILER
  \*------------------------------*/

  $app['mandrill.token'] = MANDRILL_API_KEY;
  $app['mailer'] = $app->share(function($app) {
    return new Mandrill($app['mandrill.token']);
  });


  /*------------------------------*\
              TWIG
  \*------------------------------*/
  $app->register(new Silex\Provider\TwigServiceProvider(), [
    'twig.path' => __DIR__.'/views',
    'twig.options' => [
      'strict_variables' => false
    ]
  ]);


  /*------------------------------*\
              LOGGING
  \*------------------------------*/
  $app->register(new Silex\Provider\MonologServiceProvider(), [
    'monolog.logfile' => __DIR__.'/../dev.log',
  ]);

  /*------------------------------*\
              USER
  \*------------------------------*/
  $app['user'] = $app->share(function($app) {
    if (!$app['session']->get('user_id')) {
      return null;
    }

    $stmt = $app['pdo']->prepare('SELECT * FROM user WHERE id = :id');
    $stmt->bindValue(':id', $app['session']->get('user_id'));
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
  });
}