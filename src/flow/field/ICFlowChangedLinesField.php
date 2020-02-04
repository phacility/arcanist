<?php

final class ICFlowChangedLinesField extends ICFlowField {

  private $changedLines = array();
  private $maxDelStrlen = 0;

  public function getFieldKey() {
    return 'changed-lines';
  }

  public function getSummary() {
    return pht(
      "Number of lines removed and added in the branch's local HEAD commit.");
  }

  protected function getFutures(ICFlowWorkspace $workspace) {
    $max_del_lines = 0;
    $git = $workspace->getGitAPI();
    foreach ($workspace->getFeatures() as $branch => $feature) {
      $local_diff = $feature->getHead()->getHeadDiff();
      if ($feature->getRevisionFirstCommit()) {
         $local_diff = $git->getAPI()->getFullGitDiff($feature->getRevisionFirstCommit()."^", $feature->getHead()->getObjectName());
      }
      if ($local_diff) {
        $md5 = md5($local_diff);
        $cache_key = "diff-{$md5}-add-del";
        $add_del = $this->cacheGet($cache_key);
        if (!$add_del) {
          $bundle = ICArcanistBundler::newFromDiff($local_diff);
          $hunks = array_mergev(mpull($bundle->getChanges(), 'getHunks'));
          $add_lines = 0;
          $del_lines = 0;
          foreach ($hunks as $hunk) {
            $add_lines += $hunk->getAddLines();
            $del_lines += $hunk->getDelLines();
          }
          $add_del = array(
            'add' => $add_lines,
            'del' => $del_lines,
          );
          $this->cacheSet($cache_key, $add_del);
        }

        $this->changedLines[$branch] = $add_del;
        if ($add_del['del'] > $max_del_lines) {
          $max_del_lines = $add_del['del'];
        }
      }
    }
    $this->maxDelStrlen = strlen(pht('%s', new PhutilNumber($max_del_lines)));
    return array();
  }

  protected function renderValues(array $values) {
    $add_lines = new PhutilNumber($values['add']);
    $del_lines = new PhutilNumber($values['del']);
    $del_str = pht('%s', $del_lines);
    $del_strlen = strlen($del_str);
    $padding = str_repeat(' ', $this->maxDelStrlen - $del_strlen);
    return tsprintf(
      '%s-<fg:red>%s</fg>:<fg:green>%s</fg>+',
      $padding,
      $del_str,
      pht('%s', $add_lines));
  }

  public function getValues(ICFlowFeature $feature) {
    $changed_lines = idx($this->changedLines, $feature->getName());
    return $changed_lines ? $changed_lines : null;
  }

}
