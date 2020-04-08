<?php

final class ArcanistFilesystemAPI
  extends ArcanistRepositoryAPI {

  public function getSourceControlSystemName() {
    return 'filesystem';
  }

  protected function buildUncommittedStatus() {
    throw new PhutilMethodNotImplementedException();
  }

  protected function buildCommitRangeStatus() {
    throw new PhutilMethodNotImplementedException();
  }

  public function getAllFiles() {
    throw new PhutilMethodNotImplementedException();
  }

  public function getBlame($path) {
    throw new PhutilMethodNotImplementedException();
  }

  public function getRawDiffText($path) {
    throw new PhutilMethodNotImplementedException();
  }

  public function getOriginalFileData($path) {
    throw new PhutilMethodNotImplementedException();
  }

  public function getCurrentFileData($path) {
    throw new PhutilMethodNotImplementedException();
  }

  public function getLocalCommitInformation() {
    throw new PhutilMethodNotImplementedException();
  }

  public function getSourceControlBaseRevision() {
    throw new PhutilMethodNotImplementedException();
  }

  public function getCanonicalRevisionName($string) {
    throw new PhutilMethodNotImplementedException();
  }

  public function getBranchName() {
    throw new PhutilMethodNotImplementedException();
  }

  public function getSourceControlPath() {
    throw new PhutilMethodNotImplementedException();
  }

  public function isHistoryDefaultImmutable() {
    throw new PhutilMethodNotImplementedException();
  }

  public function supportsAmend() {
    throw new PhutilMethodNotImplementedException();
  }

  public function getWorkingCopyRevision() {
    throw new PhutilMethodNotImplementedException();
  }

  public function updateWorkingCopy() {
    throw new PhutilMethodNotImplementedException();
  }

  public function getMetadataPath() {
    throw new PhutilMethodNotImplementedException();
  }

  public function loadWorkingCopyDifferentialRevisions(
    ConduitClient $conduit,
    array $query) {
    throw new PhutilMethodNotImplementedException();
  }

  public function getRemoteURI() {
    return null;
  }

  public function supportsLocalCommits() {
    throw new PhutilMethodNotImplementedException();
  }

  protected function buildLocalFuture(array $argv) {
    $future = newv('ExecFuture', $argv);
    $future->setCWD($this->getPath());
    return $future;
  }

  public function supportsCommitRanges() {
    throw new PhutilMethodNotImplementedException();
  }

}
