<?php

class myPDO extends PDO
{
  protected $app;

  public function __construct ($app, $dsn, $username='', $password='', $options=[])
  {
    $this->app = $app;
    return parent::__construct($dsn, $username, $password, $options);
    // return parent::__construct("mysql:host={$app['pdo.host']};dbname={$app['pdo.db']};charset=UTF8", $app['pdo.user'], $app['pdo.pass'], [
    //   PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'UTF8'",
    //   PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    //  ]);
   }

  protected function prepareAndBind($sql, $params)
  {
    $stmt = $this->prepare($sql);
    $named = false;
    $unNamed = false;
    foreach((array)$params as $key => $value)
    {
      if (is_numeric($key))
      {
        $stmt->bindValue($key+1, $value); // PDO params are 1-indexed
        $unNamed = true;
      }
      else
      {
        $stmt->bindValue($key, $value);
        $named = true;
      }
    }
    if ($named && $unNamed)
    {
      throw new Exception('Dont mix named and unnamed parameters in your SQL queries');
    }
    return $stmt;
  }

  public function execute($sql, $params = [])
  {
    $this->log($sql, $params);
    return $this->prepareAndBind($sql, $params)->execute();
  }

  public function fetchOne($sql, $params = [])
  {
    $data = $this->fetchAssoc($sql, $params);
    return $data ? reset($data) : null;
  }

  public function fetchAssoc($sql, $params = [])
  {
    $this->log($sql, $params);
    $stmt = $this->prepareAndBind($sql, $params);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  protected function log($sql, $params = [])
  {
    $this->app->log($this->getBoundQueryForSqlAndParams($sql, $params));
  }

  protected function getBoundQueryForSqlAndParams($sql, $parameters = [])
  {
    $keys = [];
    $parameters = (array)$parameters;

    # build a regular expression for each parameter
    foreach ($parameters as $key => &$value)
    {
      if (is_string($key))
      {
        $keys[] = '/:' . $key . '/';
      }
      else
      {
        $keys[] = '/[?]/';
      }

      if (is_bool($value))
      {
        $value = $value ? 1 : 0;
      }
      elseif (is_string($value))
      {
        $value = '"' . $value . '"';
      }
    }

    return preg_replace($keys, $parameters, $sql, 1);
  }
}
