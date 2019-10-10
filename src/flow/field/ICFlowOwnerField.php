<?php

final class ICFlowOwnerField extends ICFlowField {

  private $users = null;

  public function getFieldKey() {
    return 'owner';
  }

  public function getSummary() {
    return pht(
      'The owner\'s username for the revision associated with HEAD of the '.
      'branch, if any.');
  }

  public function isDefaultField() {
    return false;
  }

  public function getDefaultFieldOrder() {
    return -1;
  }

  protected function getFutures(ICFlowWorkspace $workspace) {
    $features = $workspace->getFeatures();
    $author_phids = array_unique(array_filter(mpull($features,
                                                    'getAuthorPHID')));
    if (!$author_phids) {
      return array();
    }
    $user_search = $workspace->getConduit()->callMethod('user.search', array(
        'constraints' => array(
          'phids' => array_values($author_phids),
        ),
      ));
    return array('user_search' => $user_search);
  }

  protected function renderValues(array $values) {
    return idx($values, 'username');
  }

  public function getValues(ICFlowFeature $feature) {
    if ($author_phid = $feature->getAuthorPHID()) {
      if ($user = $this->getUser($author_phid)) {
        return array(
          'username' => idxv($user, array('fields', 'username'), ''),
        );
      }
    }
    return null;
  }

  private function getUser($phid) {
    if ($this->users === null) {
      $users = $this->getFutureResult('user_search', array());
      $users = idx($users, 'data', array());
      $this->users = ipull($users, null, 'phid');
    }
    return idx($this->users, $phid);
  }

}
