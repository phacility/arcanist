<?php

/**
 * Lists open revisions in Differential.
 */
final class ArcanistListWorkflow extends ArcanistWorkflow {

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

  public function getArguments() {
    return array(
      'theirs' => array(
        'help' => pht('Show their diffs'),
      ),
    );
  }

  public function run() {
    static $color_map = array(
      'Closed'          => 'cyan',
      'Needs Review'    => 'magenta',
      'Needs Revision'  => 'red',
      'Changes Planned' => 'red',
      'Accepted'        => 'green',
      'No Revision'     => 'blue',
      'Abandoned'       => 'default',
    );

    $user_id = $this->getUserPHID();
    $show_theirs = $this->getArgument('theirs', false);

    if ($show_theirs) {
      $query_params = array('status'    => 'status-open',
                            'reviewers' => array($user_id));
    } else {
      $query_params = array('status'  => 'status-open',
                            'authors' => array($user_id));
    }

    $revisions = $this->getConduit()->callMethodSynchronous('differential.query', $query_params);

    if (!$revisions) {
      echo pht('You have no open Differential revisions.')."\n";
      return 0;
    }

    $info = array();
    foreach ($revisions as $key => $revision) {
      if ($show_theirs) {
        $info[$key]['exists'] = 0;
      } else {
        $repository_api = $this->getRepositoryAPI();
        $revision_path  = Filesystem::resolvePath($revision['sourcePath']);
        $current_path   = Filesystem::resolvePath($repository_api->getPath());

        $info[$key]['exists'] = ($revision_path == $current_path) ? 1 : 0;
      }

      $info[$key]['sort'] = sprintf(
        '%d%04d%08d',
        $info[$key]['exists'],
        $revision['status'],
        $revision['id']);
      $info[$key]['statusName'] = $revision['statusName'];
      $info[$key]['color'] = idx(
        $color_map, $revision['statusName'], 'default');
    }

    $table = id(new PhutilConsoleTable())
      ->setShowHeader(false)
      ->addColumn('exists', array('title' => ''))
      ->addColumn('status', array('title' => pht('Status')))
      ->addColumn('title',  array('title' => pht('Title')));

    $info = isort($info, 'sort');
    foreach ($info as $key => $spec) {
      $revision = $revisions[$key];

      $table->addRow(array(
        'exists' => $spec['exists'] ? tsprintf('**%s**', '*') : '',
        'status' => tsprintf(
          "<fg:{$spec['color']}>%s</fg>",
          $spec['statusName']),
        'title'  => tsprintf(
          '**D%d:** %s',
          $revision['id'],
          $revision['title']),
      ));
    }

    $table->draw();
    return 0;
  }

}
