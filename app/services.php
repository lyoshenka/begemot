<?php

initServices($app);
function initServices($app) {

  /*------------------------------*\
                ROUTING
  \*------------------------------*/

  $app->register(new Silex\Provider\UrlGeneratorServiceProvider());


  /*------------------------------*\
              DATABASE
  \*------------------------------*/

  $app['pdo.db'] = MYSQL_DB;
  $app['pdo.host'] = MYSQL_HOST;
  $app['pdo.user'] = MYSQL_USER;
  $app['pdo.pass'] = MYSQL_PASS;

  $app['pdo'] = function ($app) {
    return new PDO("mysql:host={$app['pdo.host']};dbname={$app['pdo.db']};charset=UTF8", $app['pdo.user'], $app['pdo.pass'], [
      PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'UTF8'",
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
  };


  /*------------------------------*\
              SESSION
  \*------------------------------*/
  $app->register(new Silex\Provider\SessionServiceProvider());

  $app['session.db_options'] = [
    'name'        => 'bg_sess', 
    'db_table'    => 'session',
    'db_id_col'   => 'id',
    'db_data_col' => 'value',
    'db_time_col' => 'time',
  ];

  $app['session.storage.handler'] = function ($app) {
    return new Symfony\Component\HttpFoundation\Session\Storage\Handler\PdoSessionHandler(
      $app['pdo'], $app['session.db_options'], $app['session.storage.options']
    );
  };


  /*------------------------------*\
              GITHUB
  \*------------------------------*/
  $app['github.token'] = GITHUB_API_TOKEN;
  $app['github'] = function($app) {
    $client = new Github\Client();
    $client->authenticate($app['github.token'], null, Github\Client::AUTH_HTTP_TOKEN);
    return $client;
  };


  /*------------------------------*\
              MAILER
  \*------------------------------*/

  $app['mandrill.token'] = MANDRILL_API_KEY;
  $app['mailer'] = function($app) {
    return new Mandrill($app['mandrill.token']);
  };
}