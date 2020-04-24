<?php

final class ArcanistPrompt
  extends Phobject {

  private $key;
  private $workflow;
  private $description;
  private $query;

  public function setKey($key) {
    $this->key = $key;
    return $this;
  }

  public function getKey() {
    return $this->key;
  }

  public function setWorkflow(ArcanistWorkflow $workflow) {
    $this->workflow = $workflow;
    return $this;
  }

  public function getWorkflow() {
    return $this->workflow;
  }

  public function setDescription($description) {
    $this->description = $description;
    return $this;
  }

  public function getDescription() {
    return $this->description;
  }

  public function setQuery($query) {
    $this->query = $query;
    return $this;
  }

  public function getQuery() {
    return $this->query;
  }

  public function execute() {
    $workflow = $this->getWorkflow();
    if ($workflow) {
      $workflow_ok = $workflow->hasPrompt($this->getKey());
    } else {
      $workflow_ok = false;
    }

    if (!$workflow_ok) {
      throw new Exception(
        pht(
          'Prompt ("%s") is executing, but it is not properly bound to the '.
          'invoking workflow. You may have called "newPrompt()" to execute a '.
          'prompt instead of "getPrompt()". Use "newPrompt()" when defining '.
          'prompts and "getPrompt()" when executing them.',
          $this->getKey()));
    }

    $query = $this->getQuery();
    if (!strlen($query)) {
      throw new Exception(
        pht(
          'Prompt ("%s") has no query text!',
          $this->getKey()));
    }

    $options = '[y/N]';
    $default = 'N';

    try {
      phutil_console_require_tty();
    } catch (PhutilConsoleStdinNotInteractiveException $ex) {
      // TOOLSETS: Clean this up to provide more details to the user about how
      // they can configure prompts to be answered.

      // Throw after echoing the prompt so the user has some idea what happened.
      echo $query."\n";
      throw $ex;
    }

    // NOTE: We're making stdin nonblocking so that we can respond to signals
    // immediately. If we don't, and you ^C during a prompt, the program does
    // not handle the signal until fgets() returns.

    $stdin = fopen('php://stdin', 'r');
    if (!$stdin) {
      throw new Exception(pht('Failed to open stdin for reading.'));
    }

    $ok = stream_set_blocking($stdin, false);
    if (!$ok) {
      throw new Exception(pht('Unable to set stdin nonblocking.'));
    }

    echo "\n";

    $result = null;
    while (true) {
      echo tsprintf(
        '**<bg:cyan> %s </bg>** %s %s ',
        '>>>',
        $query,
        $options);

      while (true) {
        $read = array($stdin);
        $write = array();
        $except = array();

        $ok = stream_select($read, $write, $except, 1);
        if ($ok === false) {
          throw new Exception(pht('stream_select() failed!'));
        }

        $response = fgets($stdin);
        if (!strlen($response)) {
          continue;
        }

        break;
      }

      $response = trim($response);
      if (!strlen($response)) {
        $response = $default;
      }

      if (phutil_utf8_strtolower($response) == 'y') {
        $result = true;
        break;
      }

      if (phutil_utf8_strtolower($response) == 'n') {
        $result = false;
        break;
      }
    }

    if (!$result) {
      throw new ArcanistUserAbortException();
    }

  }

}
