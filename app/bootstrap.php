<?php

ini_set('error_reporting', E_ALL|E_STRICT);
ini_set('display_errors', 1);
date_default_timezone_set('UTC');
mb_internal_encoding('UTF-8');

set_error_handler(function ($type, $message, $file = null, $line = null, $context = null) {
  if ($file !== null && $line !== null)
  {
    $errorLine = explode("\n", file_get_contents($file))[$line-1];
    if (strpos($errorLine, '@') !== false && !preg_match('/@[a-z0-9-_.]+\.[a-z]{2,6}/i', $errorLine))
    {
      return; // error was probably meant to be ignored
    }
  }

  $errorConstants = [
    'E_ERROR','E_WARNING','E_PARSE','E_NOTICE','E_CORE_ERROR','E_CORE_WARNING',
    'E_COMPILE_ERROR','E_COMPILE_WARNING','E_USER_ERROR','E_USER_WARNING','E_USER_NOTICE',
    'E_STRICT','E_RECOVERABLE_ERROR','E_DEPRECATED','E_USER_DEPRECATED','E_ALL'
  ];

  $errorName = 'E_UNKNOWN('.$type.')';
  foreach($errorConstants as $constant) {
    if ($type == constant($constant)) {
      $errorName = $constant;
      break;
    }
  }
  throw new ErrorException($errorName.': '.$message, $type, 0, $file, $line);
});

try
{
  require_once __DIR__.'/../vendor/autoload.php';

  require_once __DIR__.'/config.php';
  require_once __DIR__.'/app.php';

  $app = new MyApp();
  //$app['debug'] = true;

  // Initialize Services
  require_once __DIR__.'/services.php';


  // Mount Routes
  require_once __DIR__.'/routes/main.php';
  require_once __DIR__.'/routes/github.php';

  // If no route matches, 404
  $app->error(function(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, $code) use ($app) {
    return $app['twig']->render('404.twig');
  });

  $app->error(function (\Exception $e, $code) use ($app) {
    if ($app['debug'])
    {
      return;
    }
    $app['mailer']->sendErrorEmail($e);
    return $app['twig']->render('error.twig');
  });
}
catch (Exception $e)
{
  mail(DEV_EMAIL, 'Begemot Error', $e->__toString());
}
