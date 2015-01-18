<?php

class MyApp extends Silex\Application {
  use Silex\Application\UrlGeneratorTrait;
  use Silex\Application\TwigTrait;
  use Silex\Application\MonologTrait;
  // use Silex\Application\SecurityTrait;
  // use Silex\Application\FormTrait;
  // use Silex\Application\SwiftmailerTrait;
  // use Silex\Application\TranslationTrait;

  function forward($path, $requestType = 'GET')
  {
    $subReq = Symfony\Component\HttpFoundation\Request::create($path, $requestType);
    return $this->handle($subReq, Symfony\Component\HttpKernel\HttpKernelInterface::SUB_REQUEST);
  }

  function addFlash($flashType, $message)
  {
    $this['session']->getFlashBag()->add($flashType, $message);
  }
}