<?php

/**
 * Represents a parsed commit message.
 */
final class ArcanistDifferentialCommitMessage extends Phobject {

  private $rawCorpus;
  private $revisionID;
  private $fields = array();
  private $xactions = null;

  private $gitSVNBaseRevision;
  private $gitSVNBasePath;
  private $gitSVNUUID;

  public static function newFromRawCorpus($corpus) {
    $obj = new ArcanistDifferentialCommitMessage();
    $obj->rawCorpus = $corpus;
    $obj->revisionID = $obj->parseRevisionIDFromRawCorpus($corpus);

    $pattern = '/^git-svn-id:\s*([^@]+)@(\d+)\s+(.*)$/m';
    $match = null;
    if (preg_match($pattern, $corpus, $match)) {
      $obj->gitSVNBaseRevision = $match[1].'@'.$match[2];
      $obj->gitSVNBasePath     = $match[1];
      $obj->gitSVNUUID         = $match[3];
    }

    return $obj;
  }

  public function getRawCorpus() {
    return $this->rawCorpus;
  }

  public function getRevisionID() {
    return $this->revisionID;
  }

  public function getRevisionMonogram() {
    if ($this->revisionID) {
      return 'D'.$this->revisionID;
    }
    return null;
  }

  public function pullDataFromConduit(
    ConduitClient $conduit,
    $partial = false) {

    $result = $conduit->callMethodSynchronous(
      'differential.parsecommitmessage',
      array(
        'corpus'  => $this->rawCorpus,
        'partial' => $partial,
      ));

    $this->fields = $result['fields'];

    // NOTE: This does not exist prior to late October 2017.
    $this->xactions = idx($result, 'transactions');

    if (!empty($result['errors'])) {
      throw new ArcanistDifferentialCommitMessageParserException(
        $result['errors']);
    }

    return $this;
  }

  public function getFieldValue($key) {
    if (array_key_exists($key, $this->fields)) {
      return $this->fields[$key];
    }
    return null;
  }

  public function setFieldValue($key, $value) {
    $this->fields[$key] = $value;
    return $this;
  }

  public function getFields() {
    return $this->fields;
  }

  public function getGitSVNBaseRevision() {
    return $this->gitSVNBaseRevision;
  }

  public function getGitSVNBasePath() {
    return $this->gitSVNBasePath;
  }

  public function getGitSVNUUID() {
    return $this->gitSVNUUID;
  }

  public function getChecksum() {
    $fields = array_filter($this->fields);
    ksort($fields);
    $fields = json_encode($fields);
    return md5($fields);
  }

  public function getTransactions() {
    return $this->xactions;
  }

  /**
   * Extract the revision ID from a commit message.
   *
   * @param string Raw commit message.
   * @return int|null Revision ID, if the commit message contains one.
   */
  private function parseRevisionIDFromRawCorpus($corpus) {
    $match = null;
    if (!preg_match('/^Differential Revision:\s*(.+)/im', $corpus, $match)) {
      return null;
    }

    $revision_value = trim($match[1]);
    $revision_pattern = '/^[dD]([1-9]\d*)\z/';

    // Accept a bare revision ID like "D123".
    if (preg_match($revision_pattern, $revision_value, $match)) {
      return (int)$match[1];
    }

    // Otherwise, try to find a full URI.
    $uri = new PhutilURI($revision_value);
    $path = $uri->getPath();
    $path = trim($path, '/');
    if (preg_match($revision_pattern, $path, $match)) {
      return (int)$match[1];
    }

    throw new ArcanistUsageException(
      pht(
        'Invalid "Differential Revision" field in commit message. This field '.
        'should have a revision identifier like "%s" or a Phabricator URI '.
        'like "%s", but has "%s".',
        'D123',
        'https://phabricator.example.com/D123',
        $revision_value));
  }

}
