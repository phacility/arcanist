<?php

final class ArcanistConduitCallFuture
  extends FutureProxy {

  private $engine;

  public function setEngine(ArcanistConduitEngine $engine) {
    $this->engine = $engine;
    return $this;
  }

  public function getEngine() {
    return $this->engine;
  }

  private function raiseLoginRequired() {
    $conduit_domain = $this->getConduitDomain();

    $message = array(
      tsprintf(
        "\n\n%W\n\n",
        pht(
          'You are trying to connect to a server ("%s") that you do not '.
          'have any stored credentials for, but the command you are '.
          'running requires authentication.',
          $conduit_domain)),
      tsprintf(
        "%W\n\n",
        pht(
          'To log in and save credentials for this server, run this '.
          'command:')),
      tsprintf(
        '%>',
        $this->getInstallCommand()),
    );

    $this->raiseException(
      pht('Conduit API login required.'),
      pht('LOGIN REQUIRED'),
      $message);
  }

  private function raiseInvalidAuth() {
    $conduit_domain = $this->getConduitDomain();

    $message = array(
      tsprintf(
        "\n\n%W\n\n",
        pht(
          'Your stored credentials for the server you are trying to connect '.
          'to ("%s") are not valid.',
          $conduit_domain)),
      tsprintf(
        "%W\n\n",
        pht(
          'To log in and save valid credentials for this server, run this '.
          'command:')),
      tsprintf(
        '%>',
        $this->getInstallCommand()),
    );

    $this->raiseException(
      pht('Invalid Conduit API credentials.'),
      pht('INVALID CREDENTIALS'),
      $message);
  }

  protected function didReceiveResult($result) {
    return $result;
  }

  protected function didReceiveException($exception) {

    if ($exception instanceof ConduitClientException) {
      switch ($exception->getErrorCode()) {
        case 'ERR-INVALID-SESSION':
          if (!$this->getEngine()->getConduitToken()) {
            $this->raiseLoginRequired();
          }
          break;
        case 'ERR-INVALID-AUTH':
          $this->raiseInvalidAuth();
          break;
      }
    }

    throw $exception;
  }

  private function getInstallCommand() {
    $conduit_uri = $this->getConduitURI();

    return csprintf(
      'arc install-certificate %s',
      $conduit_uri);
  }

  private function getConduitURI() {
    $conduit_uri = $this->getEngine()->getConduitURI();
    $conduit_uri = new PhutilURI($conduit_uri);
    $conduit_uri->setPath('/');

    return $conduit_uri;
  }

  private function getConduitDomain() {
    $conduit_uri = $this->getConduitURI();
    return $conduit_uri->getDomain();
  }

  private function raiseException($summary, $title, $body) {
    throw id(new ArcanistConduitAuthenticationException($summary))
      ->setTitle($title)
      ->setBody($body);
  }

}
