<?php

final class ArcanistGitUpstreamPath extends Phobject {

  private $path = array();

  const TYPE_LOCAL = 'local';
  const TYPE_REMOTE = 'remote';


  public function addUpstream($key, array $spec) {
    $this->path[$key] = $spec;
    return $this;
  }

  public function removeUpstream($key) {
    unset($this->path[$key]);
    return $this;
  }

  public function getUpstream($key) {
    return idx($this->path, $key);
  }

  public function getLength() {
    return count($this->path);
  }

  /**
   * Test if this path eventually connects to a remote.
   *
   * @return bool True if the path connects to a remote.
   */
  public function isConnectedToRemote() {
    $last = last($this->path);

    if (!$last) {
      return false;
    }

    return ($last['type'] == self::TYPE_REMOTE);
  }

  public function getLocalBranches() {
    return array_keys($this->path);
  }

  public function getRemoteBranchName() {
    if (!$this->isConnectedToRemote()) {
      return null;
    }

    return idx(last($this->path), 'name');
  }

  public function getRemoteRemoteName() {
    if (!$this->isConnectedToRemote()) {
      return null;
    }

    return idx(last($this->path), 'remote');
  }


  /**
   * If this path contains a cycle, return a description of it.
   *
   * @return list<string>|null Cycle, if the path contains one.
   */
  public function getCycle() {
    $last = last($this->path);
    if (!$last) {
      return null;
    }

    if (empty($last['cycle'])) {
      return null;
    }

    $parts = array();
    foreach ($this->path as $key => $item) {
      $parts[] = $key;
    }
    $parts[] = $item['name'];
    $parts[] = pht('...');

    return $parts;
  }

}
