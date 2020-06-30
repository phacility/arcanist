<?php

final class ArcanistRevisionRef
  extends ArcanistRef {

  const HARDPOINT_COMMITMESSAGE = 'ref.revision.commitmessage';
  const HARDPOINT_AUTHORREF = 'ref.revision.authorRef';
  const HARDPOINT_BUILDABLEREF = 'ref.revision.buildableRef';
  const HARDPOINT_PARENTREVISIONREFS = 'ref.revision.parentRevisionRefs';

  private $parameters;
  private $sources = array();

  public function getRefDisplayName() {
    return pht('Revision %s', $this->getMonogram());
  }

  protected function newHardpoints() {
    $object_list = new ArcanistObjectListHardpoint();
    return array(
      $this->newHardpoint(self::HARDPOINT_COMMITMESSAGE),
      $this->newHardpoint(self::HARDPOINT_AUTHORREF),
      $this->newHardpoint(self::HARDPOINT_BUILDABLEREF),
      $this->newTemplateHardpoint(
        self::HARDPOINT_PARENTREVISIONREFS,
        $object_list),
    );
  }

  public static function newFromConduit(array $dict) {
    $ref = new self();
    $ref->parameters = $dict;
    return $ref;
  }

  public static function newFromConduitQuery(array $dict) {
    // Mangle an older "differential.query" result to look like a modern
    // "differential.revision.search" result.

    $status_name = idx($dict, 'statusName');

    switch ($status_name) {
      case 'Abandoned':
      case 'Closed':
        $is_closed = true;
        break;
      default:
        $is_closed = false;
        break;
    }

    $value_map = array(
      '0' => 'needs-review',
      '1' => 'needs-revision',
      '2' => 'accepted',
      '3' => 'published',
      '4' => 'abandoned',
      '5' => 'changes-planned',
    );

    $color_map = array(
      'needs-review' => 'magenta',
      'needs-revision' => 'red',
      'accepted' => 'green',
      'published' => 'cyan',
      'abandoned' => null,
      'changes-planned' => 'red',
    );

    $status_value = idx($value_map, idx($dict, 'status'));
    $ansi_color = idx($color_map, $status_value);

    $date_created = null;
    if (isset($dict['dateCreated'])) {
      $date_created = (int)$dict['dateCreated'];
    }

    $dict['fields'] = array(
      'uri' => idx($dict, 'uri'),
      'title' => idx($dict, 'title'),
      'authorPHID' => idx($dict, 'authorPHID'),
      'status' => array(
        'name' => $status_name,
        'closed' => $is_closed,
        'value' => $status_value,
        'color.ansi' => $ansi_color,
      ),
      'dateCreated' => $date_created,
    );

    return self::newFromConduit($dict);
  }

  public function getMonogram() {
    return 'D'.$this->getID();
  }

  public function getStatusShortDisplayName() {
    if ($this->isStatusNeedsReview()) {
      return pht('Review');
    }
    return idxv($this->parameters, array('fields', 'status', 'name'));
  }

  public function getStatusDisplayName() {
    return idxv($this->parameters, array('fields', 'status', 'name'));
  }

  public function getStatusANSIColor() {
    return idxv($this->parameters, array('fields', 'status', 'color.ansi'));
  }

  public function getDateCreated() {
    return idxv($this->parameters, array('fields', 'dateCreated'));
  }

  public function isStatusChangesPlanned() {
    $status = $this->getStatus();
    return ($status === 'changes-planned');
  }

  public function isStatusAbandoned() {
    $status = $this->getStatus();
    return ($status === 'abandoned');
  }

  public function isStatusPublished() {
    $status = $this->getStatus();
    return ($status === 'published');
  }

  public function isStatusAccepted() {
    $status = $this->getStatus();
    return ($status === 'accepted');
  }

  public function isStatusNeedsReview() {
    $status = $this->getStatus();
    return ($status === 'needs-review');
  }

  public function getStatus() {
    return idxv($this->parameters, array('fields', 'status', 'value'));
  }

  public function isClosed() {
    return idxv($this->parameters, array('fields', 'status', 'closed'));
  }

  public function getURI() {
    $uri = idxv($this->parameters, array('fields', 'uri'));

    if ($uri === null) {
      // TODO: The "uri" field was added at the same time as this callsite,
      // so we may not have it yet if the server is running an older version
      // of Phabricator. Fake our way through.

      $uri = '/'.$this->getMonogram();
    }

    return $uri;
  }

  public function getFullName() {
    return pht('%s: %s', $this->getMonogram(), $this->getName());
  }

  public function getID() {
    return (int)idx($this->parameters, 'id');
  }

  public function getPHID() {
    return idx($this->parameters, 'phid');
  }

  public function getDiffPHID() {
    return idxv($this->parameters, array('fields', 'diffPHID'));
  }

  public function getName() {
    return idxv($this->parameters, array('fields', 'title'));
  }

  public function getAuthorPHID() {
    return idxv($this->parameters, array('fields', 'authorPHID'));
  }

  public function addSource(ArcanistRevisionRefSource $source) {
    $this->sources[] = $source;
    return $this;
  }

  public function getSources() {
    return $this->sources;
  }

  public function getCommitMessage() {
    return $this->getHardpoint(self::HARDPOINT_COMMITMESSAGE);
  }

  public function getAuthorRef() {
    return $this->getHardpoint(self::HARDPOINT_AUTHORREF);
  }

  public function getParentRevisionRefs() {
    return $this->getHardpoint(self::HARDPOINT_PARENTREVISIONREFS);
  }

  public function getBuildableRef() {
    return $this->getHardpoint(self::HARDPOINT_BUILDABLEREF);
  }

  protected function buildRefView(ArcanistRefView $view) {
    $view
      ->setObjectName($this->getMonogram())
      ->setTitle($this->getName());
  }

}
