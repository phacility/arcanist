<?php

final class ArcanistBookmarksWorkflow
  extends ArcanistMarkersWorkflow {

  public function getWorkflowName() {
    return 'bookmarks';
  }

  public function getWorkflowArguments() {
    return array();
  }

  public function getWorkflowInformation() {
    $help = pht(<<<EOHELP
Lists bookmarks in the working copy, annotated with additional information
about review status.
EOHELP
      );

    return $this->newWorkflowInformation()
      ->setSynopsis(
        pht('Show an enhanced view of bookmarks in the working copy.'))
      ->addExample(pht('**bookmarks**'))
      ->setHelp($help);
  }

  protected function getWorkflowMarkerType() {
    $api = $this->getRepositoryAPI();
    $marker_type = ArcanistMarkerRef::TYPE_BOOKMARK;

    if (!$this->hasMarkerTypeSupport($marker_type)) {
      throw new PhutilArgumentUsageException(
        pht(
          'The version control system ("%s") in the current working copy '.
          'does not support bookmarks.',
          $api->getSourceControlSystemName()));
    }

    return $marker_type;
  }

}
