<?php

final class ArcanistConduitEngine
  extends Phobject {

  private $conduitURI;
  private $conduitToken;

  private $conduitTimeout;
  private $basicAuthUser;
  private $basicAuthPass;

  private $client;
  private $callKey = 0;
  private $activeFutures = array();
  private $resolvedFutures = array();

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

  public function setBasicAuthUser($basic_auth_user) {
    $this->basicAuthUser = $basic_auth_user;
    return $this;
  }

  public function getBasicAuthUser() {
    return $this->basicAuthUser;
  }

  public function setBasicAuthPass($basic_auth_pass) {
    $this->basicAuthPass = $basic_auth_pass;
    return $this;
  }

  public function getBasicAuthPass() {
    return $this->basicAuthPass;
  }

  public function newCall($method, array $parameters) {
    if ($this->conduitURI == null) {
      $this->raiseURIException();
    }

    $next_key = ++$this->callKey;

    return id(new ArcanistConduitCall())
      ->setKey($next_key)
      ->setEngine($this)
      ->setMethod($method)
      ->setParameters($parameters);
  }

  public function newFuture(ArcanistConduitCall $call) {
    $method = $call->getMethod();
    $parameters = $call->getParameters();

    $future = $this->getClient()->callMethod($method, $parameters);
    $this->activeFutures[$call->getKey()] = $future;
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

      $basic_user = $this->getBasicAuthUser();
      $basic_pass = $this->getBasicAuthPass();
      if ($basic_user !== null || $basic_pass !== null) {
        $client->setBasicAuthCredentials($basic_user, $basic_pass);
      }

      $token = $this->getConduitToken();
      if ($token) {
        $client->setConduitToken($this->getConduitToken());
      }
    }

    return $client;
  }

  public function resolveFuture($key) {
    if (isset($this->resolvedFutures[$key])) {
      return;
    }

    if (!isset($this->activeFutures[$key])) {
      throw new Exception(
        pht(
          'No future with key "%s" is present in pool.',
          $key));
    }

    $iterator = new FutureIterator($this->activeFutures);
    foreach ($iterator as $future_key => $future) {
      $this->resolvedFutures[$future_key] = $future;
      unset($this->activeFutures[$future_key]);
      if ($future_key == $key) {
        break;
      }
    }

    return;
  }

  private function raiseURIException() {
    $list = id(new PhutilConsoleList())
      ->addItem(
        pht(
          'Run in a working copy with "phabricator.uri" set in ".arcconfig".'))
      ->addItem(
        pht(
          'Set a default URI with `arc set-config default <uri>`.'))
      ->addItem(
        pht(
          'Specify a URI explicitly with `--conduit-uri=<uri>`.'));

    $block = id(new PhutilConsoleBlock())
      ->addParagraph(
        pht(
          'This command needs to communicate with Phabricator, but no '.
          'Phabricator URI is configured.'))
      ->addList($list);

    throw new ArcanistUsageException($block->drawConsoleString());
  }

}
