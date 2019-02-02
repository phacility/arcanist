<?php

final class ArcanistAnoidWorkflow
  extends ArcanistWorkflow {

  public function getWorkflowName() {
    return 'anoid';
  }

  public function getWorkflowInformation() {
    $help = pht(
      'Use your skills as a starship pilot to escape from the Arcanoid. '.
      'System requirements: a color TTY with character resolution 32x15 or '.
      'greater.');

    return $this->newWorkflowInformation()
      ->setHelp($help);
  }

  public function getWorkflowArguments() {
    return array();
  }

  public function runWorkflow() {
    $root_path = dirname(phutil_get_library_root('arcanist'));
    $game_path = $root_path.'/scripts/breakout.py';
    return phutil_passthru('%s', $game_path);
  }

}
