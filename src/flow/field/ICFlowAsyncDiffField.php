<?php

final class ICFlowAsyncDiffField extends ICFlowField {

  private $buildables = null;

  public function getFieldKey() {
    return 'async-diff';
  }

  public function getSummary() {
    return pht('The aggregate status of remote builds for the latest diff published on this revision.');
  }

  public function isDefaultField() {
    return false;
  }

  protected function getFutures(ICFlowWorkspace $workspace) {
    $features = $workspace->getFeatures();
    $revision_phids = array_unique(array_filter(mpull($features, 'getActiveDiffPHID')));
    if (!$revision_phids) {
      return [];
    }
    $buildable_search = $workspace->getConduit()->callMethod('harbormaster.querybuildables', [
        'buildablePHIDs' => $revision_phids,
        'manualBuildables' => false,
      ]);
    return ['buildable_search' => $buildable_search];
  }

  protected function renderValues(array $values) {
    $status = idx($values, 'status');
    ICHarbormasterBuildableStatus::
      formatConsoleGlyphForBuildableStatusString($status);
  }

  public function getValues(ICFlowFeature $feature) {
    if ($revision_phid = $feature->getRevisionPHID()) {
      if ($build = $this->getBuildable($revision_phid)) {
        $status = idx($build, 'buildableStatus');
        return array('status' => $status);
      }
    }
    return null;
  }

  private function getBuildable($revision_phid) {
    if ($this->buildables === null) {
      $buildables = $this->getFutureResult('buildable_search', []);
      $buildables = idx($buildables, 'data', []);
      $this->buildables = ipull($buildables, null, 'containerPHID');
    }
    return idx($this->buildables, $revision_phid);
  }

}
