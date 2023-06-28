<?php

final class ArcanistLogMessage
  extends Phobject {

  private $label;
  private $message;
  private $color;
  private $channel;

  public function setLabel($label) {
    $this->label = $label;
    return $this;
  }

  public function getLabel() {
    return $this->label;
  }

  public function setColor($color) {
    $this->color = $color;
    return $this;
  }

  public function getColor() {
    return $this->color;
  }

  public function setChannel($channel) {
    $this->channel = $channel;
    return $this;
  }

  public function getChannel() {
    return $this->channel;
  }

  public function setMessage($message) {
    $this->message = $message;
    return $this;
  }

  public function getMessage() {
    return $this->message;
  }

}
