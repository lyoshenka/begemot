<?php

class myPDO
{
  protected $pdoInst;

  public function __construct($dsn,$user,$pass,$options)
  {
    $this->pdoInst = new PDO($dsn,$user,$pass,$options);
  }

  public function exec($statement)
  {
    $return = $this->pdoInst->exec($statement);
    if ($return === false)
    {
      list($sqlError, $driverError, $driverMessage) = $this->pdoInst->errorInfo();
      throw new PDOException("SQL ERROR $sqlError, DRIVER ERROR $driverError ($driverMessage)\n\nStatement: $statement");
    }
    return $return;
  }

  public function __call($name, $arguments)
  {
    // Pass call through to PDO
    return call_user_method_array($name, $this->pdoInst, $arguments);
  }
}

$app->register(new Silex\Provider\SessionServiceProvider());

$app['pdo.db'] = MYSQL_DB;
$app['pdo.host'] = MYSQL_HOST;
$app['pdo.user'] = MYSQL_USER;
$app['pdo.pass'] = MYSQL_PASS;

$app['session.db_options'] = array(
  'db_table'      => 'session',
  'db_id_col'     => 'id',
  'db_data_col'   => 'value',
  'db_time_col'   => 'time',
);

$app['pdo'] = function ($app) {
  return new myPDO("mysql:host={$app['pdo.host']};dbname={$app['pdo.db']};charset=UTF8", $app['pdo.user'], $app['pdo.pass'], [
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'UTF8'"
  ]);
};

$app['session.storage.handler'] = function ($app) {
  return new Symfony\Component\HttpFoundation\Session\Storage\Handler\PdoSessionHandler(
    $app['pdo'], $app['session.db_options'], $app['session.storage.options']
  );
};