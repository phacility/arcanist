<?php

final class ArcanistAliasEffect
  extends Phobject {

  private $type;
  private $command;
  private $arguments;
  private $message;

  const EFFECT_MISCONFIGURATION = 'misconfiguration';
  const EFFECT_SHELL = 'shell';
  const EFFECT_RESOLUTION = 'resolution';
  const EFFECT_SUGGEST = 'suggest';
  const EFFECT_OVERRIDDE = 'override';
  const EFFECT_ALIAS = 'alias';
  const EFFECT_NOTFOUND = 'not-found';
  const EFFECT_CYCLE = 'cycle';
  const EFFECT_STACK = 'stack';
  const EFFECT_IGNORED = 'ignored';

  public function setType($type) {
    $this->type = $type;
    return $this;
  }

  public function getType() {
    return $this->type;
  }

  public function setCommand($command) {
    $this->command = $command;
    return $this;
  }

  public function getCommand() {
    return $this->command;
  }

  public function setArguments(array $arguments) {
    $this->arguments = $arguments;
    return $this;
  }

  public function getArguments() {
    return $this->arguments;
  }

  public function setMessage($message) {
    $this->message = $message;
    return $this;
  }

  public function getMessage() {
    return $this->message;
  }

}
