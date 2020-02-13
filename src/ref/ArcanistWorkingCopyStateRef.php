<?php

final class ArcanistWorkingCopyStateRef
  extends ArcanistRef {

  private $rootDirectory;

  public function getRefIdentifier() {
    // TODO: This could check attached hardpoints and render something more
    // insightful.
    return pht('Working Copy State');
  }

  public function defineHardpoints() {
    return array(
      'commitRef' => array(
        'type' => 'ArcanistCommitRef',
      ),
      'branchRef' => array(
        'type' => 'ArcanistBranchRef',
      ),
      'revisionRefs' => array(
        'type' => 'ArcanistRevisionRef',
        'vector' => true,
      ),
    );
  }

  public function setRootDirectory($root_directory) {
    $this->rootDirectory = $root_directory;
    return $this;
  }

  public function getRootDirectory() {
    return $this->rootDirectory;
  }

  public function attachBranchRef(ArcanistBranchRef $branch_ref) {
    return $this->attachHardpoint('branchRef', $branch_ref);
  }

  public function getBranchRef() {
    return $this->getHardpoint('branchRef');
  }

  public function setCommitRef(ArcanistCommitRef $commit_ref) {
    return $this->attachHardpoint('commitRef', $commit_ref);
  }

  public function getCommitRef() {
    return $this->getHardpoint('commitRef');
  }

  public function getRevisionRefs() {
    return $this->getHardpoint('revisionRefs');
  }

  public function getRevisionRef() {
    if ($this->hasAmbiguousRevisionRefs()) {
      throw new Exception(
        pht('State has multiple ambiguous revisions refs.'));
    }

    $refs = $this->getRevisionRefs();
    if ($refs) {
      return head($refs);
    }

    return null;
  }

  public function hasAmbiguousRevisionRefs() {
    return (count($this->getRevisionRefs()) > 1);
  }

  protected function canReadHardpoint($hardpoint) {
    switch ($hardpoint) {
      case 'commitRef':
        // If we have a branch ref, we can try to read the commit ref from the
        // branch ref.
        if ($this->hasAttachedHardpoint('branchRef')) {
          if ($this->getBranchRef()->hasAttachedHardpoint('commitRef')) {
            return true;
          }
        }
        break;
    }

    return false;
  }

  protected function readHardpoint($hardpoint) {
    switch ($hardpoint) {
      case 'commitRef':
        return $this->getBranchRef()->getCommitRef();
    }

    return parent::readHardpoint($hardpoint);
  }

  protected function mergeHardpoint($hardpoint, array $src, array $new) {
    if ($hardpoint == 'revisionRefs') {
      $src = mpull($src, null, 'getID');
      $new = mpull($new, null, 'getID');

      foreach ($new as $id => $ref) {
        if (isset($src[$id])) {
          foreach ($ref->getSources() as $source) {
            $src[$id]->addSource($source);
          }
        } else {
          $src[$id] = $ref;
        }
      }

      return array_values($src);
    }

    return parent::mergeHardpoint($hardpoint, $src, $new);
  }

}
