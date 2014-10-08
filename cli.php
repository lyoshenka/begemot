#!/usr/bin/env php
<?php

set_time_limit(0);

require_once __DIR__.'/bootstrap.php'; 

$app->register(new Knp\Provider\ConsoleServiceProvider(), [
  'console.name' => 'begemot',
  'console.version' => '0.1.0',
  'console.project_directory' => __DIR__
]);

class MyCmd extends \Knp\Command\Command {
  protected function configure() {
    $this
      ->setName("do-email")
      ->setDescription("Pretend you got this email")
      ->addArgument('path', \Symfony\Component\Console\Input\InputArgument::REQUIRED, 'Path to request data')
    ;
  }
  protected function execute(Symfony\Component\Console\Input\ArgvInput $input, Symfony\Component\Console\Output\ConsoleOutput $output) {
    $path = $input->getArgument('path');
    if ($path[0] != '/')
    {
      $path = __DIR__.'/'.$path;
    }

    $data = file_get_contents($path);
    parseData($data);
  }
}

$console = $app["console"];
$console->add(new MyCmd());
$console->run();