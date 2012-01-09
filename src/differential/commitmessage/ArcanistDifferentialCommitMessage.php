<?php

/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * Represents a parsed commit message.
 *
 * @group differential
 */
class ArcanistDifferentialCommitMessage {

  private $rawCorpus;
  private $revisionID;
  private $fields;

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

  public function pullDataFromConduit(ConduitClient $conduit) {
    $result = $conduit->callMethodSynchronous(
      'differential.parsecommitmessage',
      array(
        'corpus' => $this->rawCorpus,
      ));
    if (!empty($result['errors'])) {
      throw new ArcanistDifferentialCommitMessageParserException(
        $result['errors']);
    }
    $this->fields = $result['fields'];
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
