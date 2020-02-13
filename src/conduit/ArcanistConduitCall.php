<?php

final class ArcanistConduitCall
  extends Phobject {

  private $key;
  private $engine;
  private $method;
  private $parameters;
  private $future;

  public function setKey($key) {
    $this->key = $key;
    return $this;
  }

  public function getKey() {
    return $this->key;
  }

  public function setEngine(ArcanistConduitEngine $engine) {
    $this->engine = $engine;
    return $this;
  }

  public function getEngine() {
    return $this->engine;
  }

  public function setMethod($method) {
    $this->method = $method;
    return $this;
  }

  public function getMethod() {
    return $this->method;
  }

  public function setParameters(array $parameters) {
    $this->parameters = $parameters;
    return $this;
  }

  public function getParameters() {
    return $this->parameters;
  }

  private function newFuture() {
    if ($this->future) {
      throw new Exception(
        pht(
          'Call has previously generated a future. Create a '.
          'new call object for each API method invocation.'));
    }

    $method = $this->getMethod();
    $parameters = $this->getParameters();
    $future = $this->getEngine()->newFuture($this);
    $this->future = $future;

    return $this->future;
  }

  public function resolve() {
    if (!$this->future) {
      $this->newFuture();
    }

    $this->getEngine()->resolveFuture($this->getKey());

    return $this->resolveFuture();
  }

  private function resolveFuture() {
    $future = $this->future;

    try {
      $result = $future->resolve();
    } catch (ConduitClientException $ex) {
      switch ($ex->getErrorCode()) {
        case 'ERR-INVALID-SESSION':
          if (!$this->getEngine()->getConduitToken()) {
            $this->raiseLoginRequired();
          }
          break;
        case 'ERR-INVALID-AUTH':
          $this->raiseInvalidAuth();
          break;
      }

      throw $ex;
    }

    return $result;
  }

  private function raiseLoginRequired() {
    $conduit_uri = $this->getEngine()->getConduitURI();
    $conduit_uri = new PhutilURI($conduit_uri);
    $conduit_uri->setPath('/');

    $conduit_domain = $conduit_uri->getDomain();

    $block = id(new PhutilConsoleBlock())
      ->addParagraph(
        tsprintf(
          '**<bg:red> %s </bg>**',
          pht('LOGIN REQUIRED')))
      ->addParagraph(
        pht(
          'You are trying to connect to a server ("%s") that you do not '.
          'have any stored credentials for, but the command you are '.
          'running requires authentication.',
          $conduit_domain))
      ->addParagraph(
        pht(
          'To login and save credentials for this server, run this '.
          'command:'))
      ->addParagraph(
        tsprintf(
          "    $ arc install-certificate %s\n",
          $conduit_uri));

    throw new ArcanistUsageException($block->drawConsoleString());
  }

  private function raiseInvalidAuth() {
    $conduit_uri = $this->getEngine()->getConduitURI();
    $conduit_uri = new PhutilURI($conduit_uri);
    $conduit_uri->setPath('/');

    $conduit_domain = $conduit_uri->getDomain();

    $block = id(new PhutilConsoleBlock())
      ->addParagraph(
        tsprintf(
          '**<bg:red> %s </bg>**',
          pht('INVALID CREDENTIALS')))
      ->addParagraph(
        pht(
          'Your stored credentials for this server ("%s") are not valid.',
          $conduit_domain))
      ->addParagraph(
        pht(
          'To login and save valid credentials for this server, run this '.
          'command:'))
      ->addParagraph(
        tsprintf(
          "    $ arc install-certificate %s\n",
          $conduit_uri));

    throw new ArcanistUsageException($block->drawConsoleString());
  }

}
