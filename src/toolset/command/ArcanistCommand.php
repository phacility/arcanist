<?php

final class ArcanistCommand
  extends Phobject {

  private $logEngine;
  private $executableFuture;
  private $resolveOnError = false;

  public function setExecutableFuture(PhutilExecutableFuture $future) {
    $this->executableFuture = $future;
    return $this;
  }

  public function getExecutableFuture() {
    return $this->executableFuture;
  }

  public function setLogEngine(ArcanistLogEngine $log_engine) {
    $this->logEngine = $log_engine;
    return $this;
  }

  public function getLogEngine() {
    return $this->logEngine;
  }

  public function setResolveOnError($resolve_on_error) {
    $this->resolveOnError = $resolve_on_error;
    return $this;
  }

  public function getResolveOnError() {
    return $this->resolveOnError;
  }

  public function execute() {
    $log = $this->getLogEngine();
    $future = $this->getExecutableFuture();
    $command = $future->getCommand();

    $log->writeNewline();

    $log->writeStatus(
      ' $ ',
      tsprintf('**%s**', phutil_string_cast($command)));

    $log->writeNewline();

    $err = $future->resolve();

    $log->writeNewline();

    if ($err && !$this->getResolveOnError()) {
      $log->writeError(
        pht('ERROR'),
        pht(
          'Command exited with error code %d.',
          $err));

      throw new CommandException(
        pht('Command exited with nonzero error code.'),
        $command,
        $err,
        '',
        '');
    }

    return $err;
  }
}
