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

  public function sendErrorEmail(\Exception $error)
  {
    $this->messages->send([
      'from_email' => $this->app['config.system_email'],
      'from_name' => 'Begemot',
      'track_clicks' => false,
      'track_opens' => false,
      'to' => [['type' => 'to', 'email' => $this->app['config.support_email']]],
      'subject' => 'Error in Begemot',
      'html' => $this->app['twig']->render('emails/internal_error.twig', [
        'request' => $this->app['request'],
        'error' => $error
      ]),
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
