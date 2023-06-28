<?php

final class ArcanistLandCommit
  extends Phobject {

  private $hash;
  private $summary;
  private $displaySummary;
  private $parents;
  private $explicitRevisionRef;
  private $revisionRef = false;
  private $parentCommits;
  private $isHeadCommit;
  private $isImplicitCommit;
  private $relatedRevisionRefs = array();

  private $directSymbols = array();
  private $indirectSymbols = array();

  public function setHash($hash) {
    $this->hash = $hash;
    return $this;
  }

  public function getHash() {
    return $this->hash;
  }

  public function setSummary($summary) {
    $this->summary = $summary;
    return $this;
  }

  public function getSummary() {
    return $this->summary;
  }

  public function getDisplaySummary() {
    if ($this->displaySummary === null) {
      $this->displaySummary = id(new PhutilUTF8StringTruncator())
        ->setMaximumGlyphs(64)
        ->truncateString($this->getSummary());
    }
    return $this->displaySummary;
  }

  public function setParents(array $parents) {
    $this->parents = $parents;
    return $this;
  }

  public function getParents() {
    return $this->parents;
  }

  public function addDirectSymbol(ArcanistLandSymbol $symbol) {
    $this->directSymbols[] = $symbol;
    return $this;
  }

  public function getDirectSymbols() {
    return $this->directSymbols;
  }

  public function addIndirectSymbol(ArcanistLandSymbol $symbol) {
    $this->indirectSymbols[] = $symbol;
    return $this;
  }

  public function getIndirectSymbols() {
    return $this->indirectSymbols;
  }

  public function setExplicitRevisionref(ArcanistRevisionRef $ref) {
    $this->explicitRevisionRef = $ref;
    return $this;
  }

  public function getExplicitRevisionref() {
    return $this->explicitRevisionRef;
  }

  public function setParentCommits(array $parent_commits) {
    $this->parentCommits = $parent_commits;
    return $this;
  }

  public function getParentCommits() {
    return $this->parentCommits;
  }

  public function setIsHeadCommit($is_head_commit) {
    $this->isHeadCommit = $is_head_commit;
    return $this;
  }

  public function getIsHeadCommit() {
    return $this->isHeadCommit;
  }

  public function setIsImplicitCommit($is_implicit_commit) {
    $this->isImplicitCommit = $is_implicit_commit;
    return $this;
  }

  public function getIsImplicitCommit() {
    return $this->isImplicitCommit;
  }

  public function getAncestorRevisionPHIDs() {
    $phids = array();

    foreach ($this->getParentCommits() as $parent_commit) {
      $phids += $parent_commit->getAncestorRevisionPHIDs();
    }

    $revision_ref = $this->getRevisionRef();
    if ($revision_ref) {
      $phids[$revision_ref->getPHID()] = $revision_ref->getPHID();
    }

    return $phids;
  }

  public function getRevisionRef() {
    if ($this->revisionRef === false) {
      $this->revisionRef = $this->newRevisionRef();
    }

    return $this->revisionRef;
  }

  private function newRevisionRef() {
    $revision_ref = $this->getExplicitRevisionRef();
    if ($revision_ref) {
      return $revision_ref;
    }

    $parent_refs = array();
    foreach ($this->getParentCommits() as $parent_commit) {
      $parent_ref = $parent_commit->getRevisionRef();
      if ($parent_ref) {
        $parent_refs[$parent_ref->getPHID()] = $parent_ref;
      }
    }

    if (count($parent_refs) > 1) {
      throw new Exception(
        pht(
          'Too many distinct parent refs!'));
    }

    if ($parent_refs) {
      return head($parent_refs);
    }

    return null;
  }

  public function setRelatedRevisionRefs(array $refs) {
    assert_instances_of($refs, 'ArcanistRevisionRef');
    $this->relatedRevisionRefs = $refs;
    return $this;
  }

  public function getRelatedRevisionRefs() {
    return $this->relatedRevisionRefs;
  }

}
