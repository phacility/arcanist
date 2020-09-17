<?php

/**
 * Thrown when revision state is blocking next steps.
 */
final class ArcanistRevisionStatusException extends ArcanistUsageException {

  public function __construct($message) {
    parent::__construct(
      pht($message)
    );
  }

}
