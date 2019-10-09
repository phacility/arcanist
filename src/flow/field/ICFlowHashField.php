<?php

final class ICFlowHashField extends ICFlowField {

  public function getFieldKey() {
    return 'hash';
  }

  public function getDefaultFieldOrder() {
    return 1;
  }

  public function getSummary() {
    return pht(
      'The abbreviated hash for the commit at HEAD of the branch. If the '.
      'commit has an associated differential revision and the active diff '.
      'for that revision does not exactly match the changes contained in '.
      'the local commit, the hash will be colored yellow.');
  }

  protected function renderValues(array $values) {
    $hash = substr(idx($values, 'hash'), 0, 7);
    if (idx($values, 'stale')) {
      return tsprintf('<fg:yellow>%s</fg>', $hash);
    }
    return $hash;
  }

  public function getValues(ICFlowFeature $feature) {
    $hash = $feature->getHead()->getObjectName();
    $local_diff = $feature->getHead()->getHeadDiff();
    $remote_diff = $feature->getActiveDiff();
    $values = array(
      'hash' => $hash,
      'stale' => false,
    );
    if (!$local_diff || !$remote_diff) {
      return $values;
    }
    $values['stale'] = !$this->diffsMatch($local_diff, $remote_diff);
    return $values;
  }

  private function diffsMatch($diff_a, $diff_b) {
    $md5_a = md5($diff_a);
    $md5_b = md5($diff_b);
    $cache_key = "diffs-{$md5_a}-{$md5_b}-match";
    $match = $this->cacheGet($cache_key);
    if ($match !== null) {
      return $match;
    }
    $patch_a = ICArcanistBundler::getUnifiedDiff($diff_a);
    $patch_b = ICArcanistBundler::getUnifiedDiff($diff_b);
    $match = $patch_a === $patch_b;
    $this->cacheSet($cache_key, $match);
    return $match;
  }

}
