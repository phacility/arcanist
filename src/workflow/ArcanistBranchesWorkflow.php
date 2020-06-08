<?php

final class ArcanistBranchesWorkflow
  extends ArcanistMarkersWorkflow {

  public function getWorkflowName() {
    return 'branches';
  }

  public function getWorkflowArguments() {
    return array();
  }

  public function getWorkflowInformation() {
    $help = pht(<<<EOHELP
Lists branches in the working copy, annotated with additional information
about review status.
EOHELP
      );

    return $this->newWorkflowInformation()
      ->setSynopsis(
        pht('Show an enhanced view of branches in the working copy.'))
      ->addExample(pht('**branches**'))
      ->setHelp($help);
  }

  protected function getWorkflowMarkerType() {
    $api = $this->getRepositoryAPI();
    $marker_type = ArcanistMarkerRef::TYPE_BRANCH;

    if (!$this->hasMarkerTypeSupport($marker_type)) {
      throw new PhutilArgumentUsageException(
        pht(
          'The version control system ("%s") in the current working copy '.
          'does not support branches.',
          $api->getSourceControlSystemName()));
    }

    return $marker_type;
  }

}
