<?php

final class ArcanistLogEngine
  extends Phobject {

  private $showTraceMessages;

  public function setShowTraceMessages($show_trace_messages) {
    $this->showTraceMessages = $show_trace_messages;
    return $this;
  }

  public function getShowTraceMessages() {
    return $this->showTraceMessages;
  }

  public function newMessage() {
    return new ArcanistLogMessage();
  }

  private function writeBytes($bytes) {
    fprintf(STDERR, '%s', $bytes);
    return $this;
  }

  public function writeNewline() {
    return $this->writeBytes("\n");
  }

  public function writeMessage(ArcanistLogMessage $message) {
    $color = $message->getColor();

    $this->writeBytes(
      tsprintf(
        "**<bg:".$color."> %s </bg>** %s\n",
        $message->getLabel(),
        $message->getMessage()));

    return $message;
  }

  public function writeWarning($label, $message) {
    return $this->writeMessage(
      $this->newMessage()
        ->setColor('yellow')
        ->setLabel($label)
        ->setMessage($message));
  }

  public function writeError($label, $message) {
    return $this->writeMessage(
      $this->newMessage()
        ->setColor('red')
        ->setLabel($label)
        ->setMessage($message));
  }

  public function writeSuccess($label, $message) {
    return $this->writeMessage(
      $this->newMessage()
        ->setColor('green')
        ->setLabel($label)
        ->setMessage($message));
  }

  public function writeStatus($label, $message) {
    return $this->writeMessage(
      $this->newMessage()
        ->setColor('blue')
        ->setLabel($label)
        ->setMessage($message));
  }

  public function writeTrace($label, $message) {
    $trace = $this->newMessage()
      ->setColor('magenta')
      ->setLabel($label)
      ->setMessage($message);

    if ($this->getShowTraceMessages()) {
      $this->writeMessage($trace);
    }

    return $trace;
  }

  public function writeHint($label, $message) {
    return $this->writeMessage(
      $this->newMessage()
        ->setColor('cyan')
        ->setLabel($label)
        ->setMessage($message));
  }

  public function writeWaitingForInput() {
    if (!phutil_is_interactive()) {
      return;
    }

    $this->writeStatus(
      pht('INPUT'),
      pht('Waiting for input on stdin...'));
  }

}
