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

    throw new PhutilArgumentUsageException($block->drawConsoleString());
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

    throw new PhutilArgumentUsageException($block->drawConsoleString());
  }

  protected function didReceiveResult($result) {
    return $result;
  }

  protected function didReceiveException($exception) {
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

    throw $exception;
  }

}
