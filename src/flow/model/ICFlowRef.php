<?php

final class ICFlowRef extends Phobject {

  private $fields;
  private $headDiff;

  private function __construct() {}

  public static function newFromFields(array $fields) {
    $ref = new self();
    $ref->fields = $fields;
    return $ref;
  }

  private function assertField($field) {
    if (isset($this->fields[$field])) {
      return idx($this->fields, $field);
    }
    $method = idx(debug_backtrace(), 1);
    $method = idx($method, 'function');
    throw new ICFlowRefMissingFieldException($field, $method);
  }

  public function getTracking() {
    $field = $this->assertField('upstream:track');
    if (!$field) {
      return array(0, 0);
    }
    $ahead_matches = array();
    preg_match('/ahead ([0-9]+)/', $field, $ahead_matches);
    $ahead = idx($ahead_matches, 1, 0);
    $behind_matches = array();
    preg_match('/behind ([0-9]+)/', $field, $behind_matches);
    $behind = idx($behind_matches, 1, 0);
    return array($ahead, $behind);
  }

  public function getEpoch() {
    $field = $this->assertField('committerdate:raw');
    return (int)idx(explode(' ', $field), 0);
  }

  public function getUpstream() {
    return $this->assertField('upstream:short');
  }

  public function getBody() {
    return $this->assertField('body');
  }

  public function getSubject() {
    return $this->assertField('subject');
  }

  public function isHEAD() {
    return $this->assertField('HEAD') == '*';
  }

  public function getTree() {
    return $this->assertField('tree');
  }

  public function getParent() {
    return $this->assertField('parent');
  }

  public function getName() {
    return $this->assertField('refname:short');
  }

  public function getObjectType() {
    return $this->assertField('objecttype');
  }

  public function getObjectName() {
    return $this->assertField('objectname');
  }

  public function attachHeadDiff($diff) {
    $this->headDiff = $diff;
    return $this;
  }

  public function getHeadDiff() {
    return $this->headDiff;
  }

}
