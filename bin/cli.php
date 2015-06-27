#!/usr/bin/env php
<?php

set_time_limit(0);

$appDir = __DIR__.'/../app';

require_once "$appDir/bootstrap.php";

$app->register(new Knp\Provider\ConsoleServiceProvider(), [
  'console.name' => 'begemot',
  'console.version' => '1',
  'console.project_directory' => $appDir
]);


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

    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');


    $output->writeln('creating user table');
    $pdo->exec('DROP TABLE IF EXISTS user');
    $pdo->exec("CREATE TABLE `user` (
        `id` bigint(11) unsigned NOT NULL AUTO_INCREMENT,
        `github_token` varchar(100),
        `github_token_scope` varchar (100),
        `github_repo` varchar(100),
        `github_branch` varchar(100),
        `posts_path` varchar(255),
        `created_at` datetime NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY github_token_idx (github_token)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8"
    );

    $output->writeln('creating email table');
    $pdo->exec('DROP TABLE IF EXISTS email');
    $pdo->exec("CREATE TABLE `email` (
        `id` bigint(11) unsigned NOT NULL AUTO_INCREMENT,
        `user_id` bigint(11) unsigned NOT NULL,
        `email` varchar(255) NOT NULL,
        `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`),
        UNIQUE KEY `email_idx` (`email`),
        KEY `user_id_idx` (`user_id`),
        CONSTRAINT `email_user_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
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

    $output->writeln('creating onetime_login table');
    $pdo->exec('DROP TABLE IF EXISTS onetime_login');
    $pdo->exec("CREATE TABLE `onetime_login` (
        `id` bigint(11) unsigned NOT NULL AUTO_INCREMENT,
        `user_id` bigint(11) unsigned NOT NULL,
        `hash` VARCHAR(40) NOT NULL,
        `created_at` DATETIME NOT NULL,
        PRIMARY KEY (`id`),
        KEY `user_id_idx` (`user_id`),
        CONSTRAINT `onetime_login_user_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8"
    );


    $output->writeln('creating event table');
    $pdo->exec('DROP TABLE IF EXISTS event');
    $pdo->exec("CREATE TABLE `event` (
        `id` bigint(11) unsigned NOT NULL AUTO_INCREMENT,
        `user_id` bigint(11) unsigned,
        `created_at` DATETIME NOT NULL,
        `type` varchar(40) NOT NULL,
        `description` varchar(255),
        PRIMARY KEY (`id`),
        KEY `user_id_idx` (`user_id`),
        CONSTRAINT `event_user_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8"
    );

  }
}

class CleanLoginsCommand extends \Knp\Command\Command {
  protected function configure() {
    $this
      ->setName("clean-logins")
      ->setDescription("Delete logins that have expired")
    ;
  }

  protected function execute(Symfony\Component\Console\Input\InputInterface $input, Symfony\Component\Console\Output\OutputInterface $output) {
    $app = $this->getSilexApplication();
    $count = $app['pdo']->execute('DELETE FROM onetime_login WHERE created_at < SUBTIME(?, "01:00:00")', [date('Y-m-d H:i:s')]);
    $output->writeln('Deleted ' . $count . ' expired logins.');
  }
}


$app['console']->add(new InitializeDatabaseCommand());
$app['console']->add(new CleanLoginsCommand());
$app['console']->run();
