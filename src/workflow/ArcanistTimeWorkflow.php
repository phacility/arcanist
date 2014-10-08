<?php

/**
 * Show time being tracked in Phrequent
 */
final class ArcanistTimeWorkflow extends ArcanistPhrequentWorkflow {

  public function getWorkflowName() {
    return 'time';
  }

  public function getCommandSynopses() {
    return phutil_console_format(<<<EOTEXT
      **time**
EOTEXT
      );
  }

  public function getCommandHelp() {
    return phutil_console_format(<<<EOTEXT
Show what you're currently tracking in Phrequent.
EOTEXT
      );
  }

  public function requiresConduit() {
    return true;
  }

  public function desiresWorkingCopy() {
    return false;
  }

  public function requiresAuthentication() {
    return true;
  }

  public function getArguments() {
    return array(
    );
  }

  public function run() {
    $this->printCurrentTracking();
  }

}
