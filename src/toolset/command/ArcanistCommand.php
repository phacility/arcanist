<?php

final class ArcanistCommand
  extends Phobject {

  private $logEngine;
  private $executableFuture;

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

    if ($err) {
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
  }
}
