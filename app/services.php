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

  require_once __DIR__.'/db.php';

  $app['pdo.db'] = MYSQL_DB;
  $app['pdo.host'] = MYSQL_HOST;
  $app['pdo.user'] = MYSQL_USER;
  $app['pdo.pass'] = MYSQL_PASS;

  $app['pdo'] = $app->share(function ($app) {
    return new myPDO("mysql:host={$app['pdo.host']};dbname={$app['pdo.db']};charset=UTF8", $app['pdo.user'], $app['pdo.pass'], [
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
              CSS INLINER
  \*------------------------------*/

  $app['css_inliner'] = $app->share(function($app) {
    $inliner = new Northys\CSSInliner\CSSInliner();
    $inliner->addCSS(__DIR__ . '/views/emails/email_styles.css');
    return $inliner;
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
    return $app['session']->get('user_id') ?
           ($app['pdo']->fetchOne('SELECT * FROM user WHERE id = ?', $app['session']->get('user_id')) ?: null) :
           null;
  });


  /*------------------------------*\
              EVENT LOGGER
  \*------------------------------*/
  $app['log_event'] = $app->protect(function($type, $description, $userId) use($app) {
    $stmt = $app['pdo']->execute(
      'INSERT INTO event SET user_id = ?, type = ?, description = ?, created_at = NOW()',
      [$userId, $type, $description]
    );
  });


  /*------------------------------*\
              LOGIN HASH
  \*------------------------------*/
  $app['create_onetime_login'] = $app->protect(function($userId) use($app) {
    $hash = sha1(time().'mumb0jum7bo');
    $app['pdo']->execute('INSERT INTO onetime_login SET hash = ?, user_id = ?, created_at = NOW()', [$hash,$userId]);
    return $hash;
  });


  /*------------------------------*\
              LESS
  \*------------------------------*/
  $app->register(new FF\ServiceProvider\LessServiceProvider(), [
    'less.sources'     => [__DIR__.'/less/style.less'], // specify one or serveral .less files
    'less.target'      => __DIR__.'/../web/css/style.css', // specify .css target file
//    'less.target_mode' => 0775, // Optional
  ]);
}