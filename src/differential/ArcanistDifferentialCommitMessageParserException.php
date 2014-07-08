<?php

/**
 * Thrown when a commit message isn't parseable.
 */
final class ArcanistDifferentialCommitMessageParserException extends Exception {

  private $parserErrors;

  public function __construct(array $errors) {
    $this->parserErrors = $errors;
    parent::__construct(head($errors));
  }

  public function getParserErrors() {
    return $this->parserErrors;
  }

}
