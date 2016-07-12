<?php

/**
 * Thrown when the user chooses not to continue when warned that they're about
 * to do something dangerous.
 */
final class ArcanistUserAbortException extends ArcanistUsageException {

  public function __construct() {
    parent::__construct(pht('User aborted the workflow.'));
  }

}
