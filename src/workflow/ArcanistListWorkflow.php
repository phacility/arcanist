<?php

/**
 * Lists open revisions in Differential.
 *
 * @group workflow
 */
final class ArcanistListWorkflow extends ArcanistBaseWorkflow {

  public function getWorkflowName() {
    return 'list';
  }

  public function getCommandSynopses() {
    return phutil_console_format(<<<EOTEXT
      **list**
EOTEXT
      );
  }

  public function getCommandHelp() {
    return phutil_console_format(<<<EOTEXT
          Supports: git, svn, hg
          List your open Differential revisions.
EOTEXT
      );
  }

  public function requiresConduit() {
    return true;
  }

  public function requiresRepositoryAPI() {
    return true;
  }

  public function requiresAuthentication() {
    return true;
  }

  public function run() {
    $revisions = $this->getConduit()->callMethodSynchronous(
      'differential.query',
      array(
        'authors' => array($this->getUserPHID()),
        'status'  => 'status-open',
      ));

    if (!$revisions) {
      echo "You have no open Differential revisions.\n";
      return 0;
    }

    $repository_api = $this->getRepositoryAPI();

    $info = array();

    $status_len = 0;
    foreach ($revisions as $key => $revision) {
      $revision_path = Filesystem::resolvePath($revision['sourcePath']);
      $current_path  = Filesystem::resolvePath($repository_api->getPath());
      if ($revision_path == $current_path) {
        $info[$key]['here'] = 1;
      } else {
        $info[$key]['here'] = 0;
      }
      $info[$key]['sort'] = sprintf(
        '%d%04d%08d',
        $info[$key]['here'],
        $revision['status'],
        $revision['id']);
      $info[$key]['statusName'] = $revision['statusName'];
      $status_len = max(
        $status_len,
        strlen($info[$key]['statusName']));
    }

    $info = isort($info, 'sort');
    foreach ($info as $key => $spec) {
      $revision = $revisions[$key];
      printf(
        "%s %-".($status_len + 4)."s D%d: %s\n",
        $spec['here']
          ? phutil_console_format('**%s**', '*')
          : ' ',
        $spec['statusName'],
        $revision['id'],
        $revision['title']);
    }

    return 0;
  }
}
