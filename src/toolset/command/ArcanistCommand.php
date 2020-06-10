<?php

final class ArcanistCommand
  extends Phobject {

  private $logEngine;
  private $executableFuture;
  private $resolveOnError = false;
  private $displayCommand;

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

  public function setDisplayCommand($pattern /* , ... */) {
    $argv = func_get_args();
    $command = call_user_func_array('csprintf', $argv);
    $this->displayCommand = $command;
    return $this;
  }

  public function getDisplayCommand() {
    return $this->displayCommand;
  }

  public function execute() {
    $log = $this->getLogEngine();
    $future = $this->getExecutableFuture();

    $display_command = $this->getDisplayCommand();
    if ($display_command !== null) {
      $command = $display_command;
    } else {
      $command = $future->getCommand();
    }

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
