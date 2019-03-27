<?php

final class ArcanistBuildRef
  extends Phobject {

  private $parameters;

  public static function newFromConduit(array $data) {
    $ref = new self();
    $ref->parameters = $data;
    return $ref;
  }

  private function getStatusMap() {
    // The modern "harbormaster.build.search" API method returns this in the
    // "fields" list; the older API method returns it at the root level.
    if (isset($this->parameters['fields']['buildStatus'])) {
      $status = $this->parameters['fields']['buildStatus'];
    } else if (isset($this->parameters['buildStatus'])) {
      $status = $this->parameters['buildStatus'];
    } else {
      $status = 'unknown';
    }

    // We may either have an array or a scalar here. The array comes from
    // "harbormaster.build.search", or from "harbormaster.querybuilds" if
    // the server is newer than August 2016. The scalar comes from older
    // versions of that method. See PHI261.
    if (is_array($status)) {
      $map = $status;
    } else {
      $map = array(
        'value' => $status,
      );
    }

    // If we don't have a name, try to fill one in.
    if (!isset($map['name'])) {
      $name_map = array(
        'inactive' => pht('Inactive'),
        'pending' => pht('Pending'),
        'building' => pht('Building'),
        'passed' => pht('Passed'),
        'failed' => pht('Failed'),
        'aborted' => pht('Aborted'),
        'error' => pht('Error'),
        'paused' => pht('Paused'),
        'deadlocked' => pht('Deadlocked'),
        'unknown' => pht('Unknown'),
      );

      $map['name'] = idx($name_map, $map['value'], $map['value']);
    }

    // If we don't have an ANSI color code, try to fill one in.
    if (!isset($map['color.ansi'])) {
      $color_map = array(
        'failed' => 'red',
        'passed' => 'green',
      );

      $map['color.ansi'] = idx($color_map, $map['value'], 'yellow');
    }

    return $map;
  }

  public function getID() {
    return $this->parameters['id'];
  }

  public function getPHID() {
    return $this->parameters['phid'];
  }

  public function getName() {
    if (isset($this->parameters['fields']['name'])) {
      return $this->parameters['fields']['name'];
    }

    return $this->parameters['name'];
  }

  public function getStatus() {
    $map = $this->getStatusMap();
    return $map['value'];
  }

  public function getStatusName() {
    $map = $this->getStatusMap();
    return $map['name'];
  }

  public function getStatusANSIColor() {
    $map = $this->getStatusMap();
    return $map['color.ansi'];
  }

  public function getObjectName() {
    return pht('Build %d', $this->getID());
  }

  public function getBuildPlanPHID() {
    return idxv($this->parameters, array('fields', 'buildPlanPHID'));
  }

  public function isComplete() {
    switch ($this->getStatus()) {
      case 'passed':
      case 'failed':
      case 'aborted':
      case 'error':
      case 'deadlocked':
        return true;
      default:
        return false;
    }
  }

  public function isPassed() {
    return ($this->getStatus() === 'passed');
  }

  public function getStatusSortVector() {
    $status = $this->getStatus();

    // For now, just sort passed builds first.
    if ($this->isPassed()) {
      $status_class = 1;
    } else {
      $status_class = 2;
    }

    return id(new PhutilSortVector())
      ->addInt($status_class)
      ->addString($status);
  }


}
