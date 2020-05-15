<?php

final class ArcanistConduitEngine
  extends Phobject {

  private $conduitURI;
  private $conduitToken;
  private $conduitTimeout;
  private $client;

  public function isCallable() {
    return ($this->conduitURI !== null);
  }

  public function setConduitURI($conduit_uri) {
    $this->conduitURI = $conduit_uri;
    return $this;
  }

  public function getConduitURI() {
    return $this->conduitURI;
  }

  public function setConduitToken($conduit_token) {
    $this->conduitToken = $conduit_token;
    return $this;
  }

  public function getConduitToken() {
    return $this->conduitToken;
  }

  public function setConduitTimeout($conduit_timeout) {
    $this->conduitTimeout = $conduit_timeout;
    return $this;
  }

  public function getConduitTimeout() {
    return $this->conduitTimeout;
  }

  public function newCall($method, array $parameters) {
    if ($this->conduitURI == null && $this->client === null) {
      $this->raiseURIException();
    }

    return id(new ArcanistConduitCall())
      ->setEngine($this)
      ->setMethod($method)
      ->setParameters($parameters);
  }

  public function resolveCall($method, array $parameters) {
    return $this->newCall($method, $parameters)->resolve();
  }

  public function newFuture(ArcanistConduitCall $call) {
    $method = $call->getMethod();
    $parameters = $call->getParameters();

    $future = $this->getClient()->callMethod($method, $parameters);

    return $future;
  }

  private function getClient() {
    if (!$this->client) {
      $conduit_uri = $this->getConduitURI();

      $client = new ConduitClient($conduit_uri);

      $timeout = $this->getConduitTimeout();
      if ($timeout) {
        $client->setTimeout($timeout);
      }

      $token = $this->getConduitToken();
      if ($token) {
        $client->setConduitToken($this->getConduitToken());
      }

      $this->client = $client;
    }

    return $this->client;
  }

  private function raiseURIException() {
    $list = id(new PhutilConsoleList())
      ->addItem(
        pht(
          'Run in a working copy with "phabricator.uri" set in ".arcconfig".'))
      ->addItem(
        pht(
          'Set a default URI with `arc set-config phabricator.uri <uri>`.'))
      ->addItem(
        pht(
          'Specify a URI explicitly with `--config phabricator.uri=<uri>`.'));

    $block = id(new PhutilConsoleBlock())
      ->addParagraph(
        pht(
          'This command needs to communicate with Phabricator, but no '.
          'Phabricator URI is configured.'))
      ->addList($list);

    throw new ArcanistUsageException($block->drawConsoleString());
  }

  public static function newConduitEngineFromConduitClient(
    ConduitClient $client) {

    $engine = new self();
    $engine->client = $client;

    return $engine;
  }
}
