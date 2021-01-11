<?php

final class ICFlowFeature extends Phobject {

  private $head;
  private $differentialCommitMessage = null;
  // commit sha which has differential commit message, essentially where
  // differential revision starts
  private $revisionFirstCommit = null;
  private $revision;
  private $search;
  private $activeDiff;

  private function __construct() {}

  public static function newFromHead(ICFlowRef $head, ICGitAPI $git) {
    $default_branch = $git->getDefaultRemoteBranch();
    $feature = new self();
    $feature->head = $head;
    $upstream = $head->getUpstream();
    // we're not interested in branches which have no upstream
    // nor default branch
    if (!$upstream || $head->getName() == $default_branch) {
      return $feature;
    }
    // if upstream branch doesn't exists - treat remote default branch as upstream
    if (!$git->revParseVerify($upstream)) {
      echo phutil_console_format("Branch <fg:green>%s</fg> is based on ".
                                 "<fg:red>%s</fg>, but the upstream is gone. ".
                                 "Try running `arc tidy` to fix upsteam. ".
                                 "Trying to show changes based on %s ".
                                 "branch.\n",
                                 $head->getName(), $upstream, $default_branch);
      $upstream = $default_branch;
    }
    // get all git logs from HEAD to upstream branch it will be used to find
    // closest match differential revision
    $logs = $git->getGitCommitLog($upstream, $head->getObjectName());
    if (strlen($logs) == 0) {
        // most likely it is branch which was not yet arc diff'ed, do not try
        // to resolve too hard
        return $feature;
    }

    $parser = new ArcanistDiffParser();
    $logs = $parser->parseDiff($logs);
    foreach ($logs as $log) {
      $message = ArcanistDifferentialCommitMessage::newFromRawCorpus(
        $log->getMetadata('message'));
      if ($message->getRevisionID() != null) {
        $feature->differentialCommitMessage = $message;
        $feature->revisionFirstCommit = $log->getCommitHash();
        break;
      }
    }
    return $feature;
  }

  public function getRevisionField($index, $default = null) {
    if (!$this->revision) {
      return null;
    }
    return idx($this->revision, $index, $default);
  }

  public function attachRevisionData(array $revision = null) {
    $this->revision = $revision;
    return $this;
  }

  public function getActiveDiffID() {
    $diffs = $this->getRevisionField('diffs');
    return $diffs ? head($diffs) : null;
  }

  public function getActiveDiffPHID() {
    return $this->getRevisionField('activeDiffPHID');
  }

  public function getRevisionStatusName() {
    return $this->getRevisionField('statusName', '');
  }

  public function getAuthorPHID() {
    return $this->getRevisionField('authorPHID');
  }

  public function getRevisionPHID() {
    return $this->getRevisionField('phid');
  }

  public function getRevisionID() {
    if (!$this->differentialCommitMessage) {
      return null;
    }
    return $this->differentialCommitMessage->getRevisionID();
  }

  public function getRevisionFirstCommit() {
    return $this->revisionFirstCommit;
  }

  public function getSearchField($index, $default = null) {
    if (!$this->search) {
      return $default;
    }
    return idx($this->search, $index, $default);
  }

  public function attachSearchData(array $search = null) {
    $this->search = $search;
    return $this;
  }

  public function getSearchAttachment($name) {
    $attachments = $this->getSearchField('attachments', array());
    return idx($attachments, $name);
  }

  public function getDifferentialCommitMessage() {
    return $this->differentialCommitMessage;
  }

  public function getName() {
    return $this->head->getName();
  }

  public function getHead() {
    return $this->head;
  }

  public function attachActiveDiff($diff) {
    $this->activeDiff = $diff;
    return $this;
  }

  public function getActiveDiff() {
    return $this->activeDiff;
  }

}
