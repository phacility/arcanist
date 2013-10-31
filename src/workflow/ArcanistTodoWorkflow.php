<?php

/**
 * Quickly create a task
 *
 * @group workflow
 */
final class ArcanistTodoWorkflow extends ArcanistBaseWorkflow {

  public function getWorkflowName() {
    return 'todo';
  }

  public function getCommandSynopses() {
    return phutil_console_format(<<<EOTEXT
      **todo** __summary__ [__options__]
EOTEXT
      );
  }

  public function getCommandHelp() {
    return phutil_console_format(<<<EOTEXT
        Quickly create a task for yourself.
EOTEXT
      );
  }

  public function requiresConduit() {
    return true;
  }

  public function desiresWorkingCopy() {
    return true;
  }

  public function requiresAuthentication() {
    return true;
  }


  public function getArguments() {
    return array(
      '*' => 'summary',
      'cc' => array(
        'param' => 'cc',
        'short' => 'C',
        'repeat' => true,
        'help'  => 'Other users to CC on the new task.',
      ),
      'proj' => array(
        'param' => 'proj',
        'short' => 'P',
        'repeat' => true,
        'help'  => 'Assign task to this project.',
      ),
    );
  }

  public function run() {
    $summary = implode(' ', $this->getArgument('summary'));
    $ccs = $this->getArgument('cc');
    $projs = $this->getArgument('proj');
    $conduit = $this->getConduit();

    if (trim($summary) == '') {
      echo "Please provide a summary.\n";
      return;
    }

    $args = array(
      'title' => $summary,
      'ownerPHID' => $this->getUserPHID()
    );

    if ($ccs) {
      $phids = array();
      $users = $conduit->callMethodSynchronous(
        'user.query',
        array(
          'usernames' => $ccs
        ));
      foreach ($users as $user => $info) {
        $phids[] = $info['phid'];
      }
      $args['ccPHIDs'] = $phids;
    }
    if (!$projs) {
      $proj = $this->getConfigFromAnySource('todo.default_project');
      if ($proj) {
        $projs[] = $proj;
      }
    }
    if ($projs) {
      $phids = array();
      $projects = $conduit->callMethodSynchronous(
        'project.query',
        array(
          'status' => 'status-active',
        ));
      foreach ($projects as $phid => $info) {
        if (in_array($info['name'], $projs)) {
          $phids[] = $info['phid'];
        }
      }
      $args['projectPHIDs'] = $phids;
    }

    $result = $conduit->callMethodSynchronous(
      'maniphest.createtask',
      $args);

    echo phutil_console_format(
      "Created task T%s: '<fg:green>**%s**</fg>' at <fg:blue>**%s**</fg>\n",
      $result['id'],
      $result['title'],
      $result['uri']);
  }

}
