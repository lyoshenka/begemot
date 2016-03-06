<?php

class BgMailer
{

  protected $apiDomain = 'https://api.mailgun.net/v3';

  protected $app;
  protected $apiKey;
  protected $domain;

  protected $cssInliner;

  public function __construct($app, $apiKey, $domain)
  {
    $this->app = $app;
    $this->apiKey = $apiKey;
    $this->domain = $domain;

    $this->cssInliner = new Northys\CSSInliner\CSSInliner();
    $this->cssInliner->addCSS($app['root_dir'] . '/views/emails/email_styles.css');
  }

  public function sendErrorEmail(\Exception $error)
  {
    $this->send([
      'from' => 'Begemot <' . $this->app['config.system_email'] . '>',
      'to' => $this->app['config.support_email'],
      'subject' => 'Error in Begemot',
      'html' => $this->app['twig']->render('emails/internal_error.twig', [
        'request' => $this->app['request'],
        'error' => $error
      ]),
    ]);
    $this->app->log('Sent error email');
  }

  public function sendPublishSuccessEmail($email, $postTitle)
  {
    $this->send([
      'from' => 'Begemot <' . $this->app['config.system_email'] . '>',
      'to' => $email,
      'subject' => 'Post Published',
      'html' => $this->cssInliner->render($this->app['twig']->render('emails/post_received.twig', [
        'title' => $postTitle
      ])),
    ]);
    $this->app->log('Sent publish success email');
  }

  public function sendPublishErrorEmail($email, $postTitle, $errorMessage)
  {
    $this->send([
      'from' => 'Begemot <' . $this->app['config.system_email'] . '>',
      'to' => $email,
      'subject' => 'Post Error',
      'html' => $this->cssInliner->render($this->app['twig']->render('emails/post_error.twig', [
        'title' => $postTitle,
        'text' => $errorMessage
      ])),
    ]);
    $this->app->log('Sent publish error email');
  }


  protected function send($params)
  {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $this->apiDomain.'/'.$this->domain.'/messages');
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_USERPWD, "api:".$this->apiKey);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_POST, 1); 
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params)); 
    $result = json_decode(curl_exec($ch));
    curl_close($ch);
    return $result;
  }
}
