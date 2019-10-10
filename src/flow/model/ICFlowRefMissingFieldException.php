<?php

final class ICFlowRefMissingFieldException extends Exception {

  private $field;
  private $method;

  public function getField() {
    return $this->field;
  }

  public function getMethod() {
    return $this->method;
  }

  public function __construct($field, $method) {

    $message = pht(
      'Ref does not have a value for "%s", this field is required in order to '.
      'call "%s".',
      $field,
      $method);

    parent::__construct($message);

    $this->field = $field;
    $this->method = $method;
  }

}
