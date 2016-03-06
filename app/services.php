<?php

initServices($app);
function initServices($app) {

  /*------------------------------*\
              CONFIG
  \*------------------------------*/
  $app['config.system_email'] = 'begemot@begemot.grin.io';
  $app['config.post_email'] = 'post@begemot.grin.io';
  $app['config.support_email'] = 'alex@grin.io';
  $app['root_dir'] = __DIR__;


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
    return new myPDO($app, "mysql:host={$app['pdo.host']};dbname={$app['pdo.db']};charset=UTF8", $app['pdo.user'], $app['pdo.pass'], [
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

  require_once __DIR__.'/mailer.php';

  $app['mailgun.apikey'] = MAILGUN_API_KEY;
  $app['mailgun.domain'] = MAILGUN_DOMAIN;
  $app['mailer'] = $app->share(function($app) {
    return new BgMailer($app, $app['mailgun.apikey'], $app['mailgun.domain']);
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

  $app['twig']->addFilter(new Twig_SimpleFilter('substr', function ($text, $maxLength = 30, $exact = false) {
    if (strlen($text) <= $maxLength)
    {
      return $text;
    }

    $subString = substr($text, 0, $maxLength);
    if (!$exact && substr($subString,-1,1) != ' ')
    {
      $subString = substr($subString, 0, strrpos($subString, ' '));
    }

    return $subString;
  }));

  $app['twig']->addFilter(new Twig_SimpleFilter('timeago', function ($datetime) {
    $time = time() - strtotime($datetime);
    $units = array (
      31536000 => 'year',
      2592000 => 'month',
      604800 => 'week',
      86400 => 'day',
      3600 => 'hour',
      60 => 'minute',
      1 => 'second'
    );

    foreach ($units as $unit => $val)
    {
      if ($time < $unit)
      {
        continue;
      }
      $numberOfUnits = floor($time / $unit);
      return ($val == 'second') ?
             'a few seconds ago' :
             ($numberOfUnits > 1 ? $numberOfUnits : 'a') . ' ' . $val . ($numberOfUnits>1 ? 's' : '') . ' ago';
    }
  }));


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
           $app['pdo']->fetchOne('SELECT * FROM user WHERE id = ?', $app['session']->get('user_id')) :
           null;
  });


  /*------------------------------*\
              EVENT LOGGER
  \*------------------------------*/
  $app['log_event'] = $app->protect(function($type, $description, $userId) use($app) {
    $stmt = $app['pdo']->execute(
      'INSERT INTO event SET user_id = ?, type = ?, description = ?, created_at = ?',
      [$userId, $type, $description, date('Y-m-d H:i:s')]
    );
  });


  /*------------------------------*\
              LOGIN HASH
  \*------------------------------*/
  $app['create_onetime_login'] = $app->protect(function($userId) use($app) {
    $hash = sha1(time().'mumb0jum7bo');
    $app['pdo']->execute('INSERT INTO onetime_login SET hash = ?, user_id = ?, created_at = ', [$hash, $userId, date('Y-m-d H:i:s')]);
    return $hash;
  });


  /*------------------------------*\
              LESS
  \*------------------------------*/
  $app->register(new FF\ServiceProvider\LessServiceProvider(), [
    'less.sources'     => [__DIR__.'/less/style.less'], // specify one or serveral .less files
    'less.target'      => __DIR__.'/../web/css/style.css', // specify .css target file
    // 'less.target_mode' => 0444, // Optional
  ]);
}
