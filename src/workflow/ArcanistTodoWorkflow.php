<?php

/**
 * Quickly create a task.
 */
final class ArcanistTodoWorkflow extends ArcanistWorkflow {

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
        'param'  => 'cc',
        'short'  => 'C',
        'repeat' => true,
        'help'   => pht('Other users to CC on the new task.'),
      ),
      'project' => array(
        'param'  => 'project',
        'repeat' => true,
        'help'   => pht('Projects to assign to the task.'),
      ),
      'browse' => array(
        'help' => pht('After creating the task, open it in a web browser.'),
      ),
    );
  }

  public function run() {
    $summary = implode(' ', $this->getArgument('summary'));
    $ccs = $this->getArgument('cc');
    $slugs = $this->getArgument('project');

    $conduit = $this->getConduit();

    if (trim($summary) == '') {
      echo pht('Please provide a summary.')."\n";
      return;
    }

    $args = array(
      'title' => $summary,
      'ownerPHID' => $this->getUserPHID(),
    );

    if ($ccs) {
      $phids = array();
      $users = $conduit->callMethodSynchronous(
        'user.query',
        array(
          'usernames' => $ccs,
        ));
      foreach ($users as $user => $info) {
        $phids[] = $info['phid'];
      }
      $args['ccPHIDs'] = $phids;
    }

    if ($slugs) {
      $phids = array();
      $projects = $conduit->callMethodSynchronous(
        'project.query',
        array(
          'slugs' => $slugs,
        ));

      foreach ($slugs as $slug) {
        $project = idx($projects['slugMap'], $slug);

        if (!$project) {
          throw new ArcanistUsageException(pht('No such project: "%s"', $slug));
        }
        $phids[] = $project;
      }

      $args['projectPHIDs'] = $phids;
    }

    $result = $conduit->callMethodSynchronous('maniphest.createtask', $args);
    echo pht(
      "Created task %s: '%s' at %s\n",
      'T'.$result['id'],
      phutil_console_format('<fg:green>**%s**</fg>', $result['title']),
      phutil_console_format('<fg:blue>**%s**</fg>', $result['uri']));

    if ($this->getArgument('browse')) {
      $this->openURIsInBrowser(array($result['uri']));
    }

  }

}
