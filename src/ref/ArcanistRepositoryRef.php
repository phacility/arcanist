<?php

final class ArcanistRepositoryRef
  extends ArcanistRef {

  private $parameters = array();
  private $phid;
  private $browseURI;

  public function getRefDisplayName() {
    return pht('Remote Repository');
  }

  public function setPHID($phid) {
    $this->phid = $phid;
    return $this;
  }

  public function getPHID() {
    return $this->phid;
  }

  public function setBrowseURI($browse_uri) {
    $this->browseURI = $browse_uri;
    return $this;
  }

  public static function newFromConduit(array $map) {
    $ref = new self();
    $ref->parameters = $map;

    $ref->phid = $map['phid'];

    return $ref;
  }

  public function getURIs() {
    $uris = idxv($this->parameters, array('attachments', 'uris', 'uris'));

    if (!$uris) {
      return array();
    }

    $results = array();
    foreach ($uris as $uri) {
      $effective_uri = idxv($uri, array('fields', 'uri', 'effective'));
      if ($effective_uri !== null) {
        $results[] = $effective_uri;
      }
    }

    return $results;
  }

  public function getDisplayName() {
    return idxv($this->parameters, array('fields', 'name'));
  }

  public function newBrowseURI(array $params) {
    PhutilTypeSpec::checkMap(
      $params,
      array(
        'path' => 'optional string|null',
        'branch' => 'optional string|null',
        'lines' => 'optional string|null',
      ));

    foreach ($params as $key => $value) {
      if (!strlen($value)) {
        unset($params[$key]);
      }
    }

    $defaults = array(
      'path' => '/',
      'branch' => $this->getDefaultBranch(),
      'lines' => null,
    );

    $params = $params + $defaults;

    $uri_base = $this->browseURI;
    $uri_base = rtrim($uri_base, '/');

    $uri_branch = phutil_escape_uri_path_component($params['branch']);

    $uri_path = ltrim($params['path'], '/');
    $uri_path = phutil_escape_uri($uri_path);

    $uri_lines = null;
    if ($params['lines']) {
      $uri_lines = '$'.phutil_escape_uri($params['lines']);
    }

    // TODO: This construction, which includes a branch, is probably wrong for
    // Subversion.

    return "{$uri_base}/browse/{$uri_branch}/{$uri_path}{$uri_lines}";
  }

  public function getDefaultBranch() {
    $branch = idxv($this->parameters, array('fields', 'defaultBranch'));

    if ($branch === null) {
      return 'master';
    }

    return $branch;
  }

}
