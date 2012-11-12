<?php

/**
 * Represents a parsed commit message.
 *
 * @group differential
 */
final class ArcanistDifferentialCommitMessage {

  private $rawCorpus;
  private $revisionID;
  private $fields = array();

  private $gitSVNBaseRevision;
  private $gitSVNBasePath;
  private $gitSVNUUID;

  public static function newFromRawCorpus($corpus) {
    $obj = new ArcanistDifferentialCommitMessage();
    $obj->rawCorpus = $corpus;

    // Parse older-style "123" fields, or newer-style full-URI fields.
    // TODO: Remove support for older-style fields.

    $match = null;
    if (preg_match('/^Differential Revision:\s*(.*)/im', $corpus, $match)) {
      $revision_id = trim($match[1]);
      if (strlen($revision_id)) {
        if (preg_match('/^D?\d+$/', $revision_id)) {
          $obj->revisionID = (int)trim($revision_id, 'D');
        } else {
          $uri = new PhutilURI($revision_id);
          $path = $uri->getPath();
          $path = trim($path, '/');
          if (preg_match('/^D\d+$/', $path)) {
            $obj->revisionID = (int)trim($path, 'D');
          } else {
            throw new ArcanistUsageException(
              "Invalid 'Differential Revision' field. The field should have a ".
              "Phabricator URI like 'http://phabricator.example.com/D123', ".
              "but has '{$match[1]}'.");
          }
        }
      }
    }

    $pattern = '/^git-svn-id:\s*([^@]+)@(\d+)\s+(.*)$/m';
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

}
