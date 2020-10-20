<?php

final class ArcanistUpgradeWorkflow
  extends ArcanistArcWorkflow {

  public function getWorkflowName() {
    return 'upgrade';
  }

  public function getWorkflowInformation() {
    $help = pht(<<<EOTEXT
Upgrade this program to the latest version.
EOTEXT
);

    return $this->newWorkflowInformation()
      ->setSynopsis(pht('Upgrade this program to the latest version.'))
      ->addExample(pht('**upgrade**'))
      ->setHelp($help);
  }

  public function getWorkflowArguments() {
    return array();
  }

  public function runWorkflow() {
    $log = $this->getLogEngine();
    $msg = $this->getConfig("arc.upgrade.message");
    $log->writeSuccess(pht('UP TO DATE'), pht($msg));

    return 0;
  }

}
