#!/usr/bin/env php
<?php

set_time_limit(0);

require_once __DIR__.'/bootstrap.php'; 

$app->register(new Knp\Provider\ConsoleServiceProvider(), [
  'console.name' => 'begemot',
  'console.version' => '1',
  'console.project_directory' => __DIR__
]);

class DoEmailCommand extends \Knp\Command\Command {
  protected function configure() {
    $this
      ->setName("do-email")
      ->setDescription("Pretend you got this email")
      ->addArgument('path', \Symfony\Component\Console\Input\InputArgument::REQUIRED, 'Path to request data')
    ;
  }
  protected function execute(Symfony\Component\Console\Input\InputInterface $input, Symfony\Component\Console\Output\OutputInterface $output) {
    $path = $input->getArgument('path');
    if ($path[0] != '/')
    {
      $path = __DIR__.'/'.$path;
    }

    $data = file_get_contents($path);
    parseData($data);
  }
}

class InitializeDatabaseCommand extends \Knp\Command\Command {
  protected function configure() {
    $this
      ->setName("init-db")
      ->setDescription("Initialize the database")
    ;
  }
  protected function execute(Symfony\Component\Console\Input\InputInterface $input, Symfony\Component\Console\Output\OutputInterface $output) {
    $app = $this->getSilexApplication();
    $pdo = $app['pdo'];

    $output->writeln('creating user table');

    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

    $pdo->exec('DROP TABLE IF EXISTS user');
    $pdo->exec("CREATE TABLE `user` (
        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        `github_token` varchar(100),
        `github_token_scope` varchar (100),
        `created_at` datetime NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY github_token_idx (github_token)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8"
    );

    $output->writeln('creating email table');

    $pdo->exec('DROP TABLE IF EXISTS email');
    $pdo->exec("CREATE TABLE `email` (
        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        `user_id` bigint(20) unsigned NOT NULL,
        `email` varchar(255) NOT NULL,
        `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`),
        UNIQUE KEY `email_idx` (`email`),
        KEY `user_id_idx` (`user_id`),
        CONSTRAINT `user_id_constraint` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8"
    );

    $output->writeln('creating session table');

    $pdo->exec('DROP TABLE IF EXISTS session');
    $pdo->exec("CREATE TABLE `session` (
        `id` VARCHAR(40) NOT NULL,
        `time` INT(10) UNSIGNED NOT NULL,
        `value` text,
        PRIMARY KEY (`id`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8"
    );
  }
}


$console = $app['console'];
$console->add(new DoEmailCommand());
$console->add(new InitializeDatabaseCommand());
$console->run();