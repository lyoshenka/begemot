<?php

class BgMailer extends Mandrill
{

  protected $app;
  protected $cssInliner;

  public function __construct($app, $apikey=null)
  {
    $this->app = $app;

    $this->cssInliner = new Northys\CSSInliner\CSSInliner();
    $this->cssInliner->addCSS($app['root_dir'] . '/views/emails/email_styles.css');

    return parent::__construct($apikey);
  }

  public function sendJoinEmail()
  {
    $this->messages->send([
      'from_email' => $this->app['config.system_email'],
      'from_name' => 'Begemot',
      'track_clicks' => true,
      'track_opens' => true,
      'to' => [['type' => 'to', 'email' => $email]],
      'subject' => 'Welcome to Begemot',
      'html' => $this->cssInliner->render($this->app['twig']->render('emails/login.twig', [
        'url' => $this->app->url('app')
      ])),
    ]);
  }

  public function sendPublishSuccessEmail($email, $postTitle)
  {
    $this->messages->send([
      'from_email' => $this->app['config.system_email'],
      'from_name' => 'Begemot',
      'track_clicks' => false,
      'track_opens' => true,
      'to' => [['type' => 'to', 'email' => $email]],
      'subject' => 'Post Published',
      'html' => $this->cssInliner->render($this->app['twig']->render('emails/post_received.twig', [
        'title' => $postTitle
      ])),
    ]);
  }

  public function sendPublishErrorEmail($email, $postTitle, $errorMessage)
  {
    $this->messages->send([
      'from_email' => $this->app['config.system_email'],
      'from_name' => 'Begemot',
      'track_clicks' => false,
      'track_opens' => true,
      'to' => [['type' => 'to', 'email' => $email]],
      'subject' => 'Post Error',
      'html' => $this->cssInliner->render($this->app['twig']->render('emails/post_received.twig', [
        'title' => $postTitle,
        'text' => $errorMessage
      ])),
    ]);
  }
}
