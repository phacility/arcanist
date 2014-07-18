<?php

/**
 * Quickly create a task.
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
        'param'  => 'cc',
        'short'  => 'C',
        'repeat' => true,
        'help'   => pht('Other users to CC on the new task.'),
      ),
      'owner' => array(
        'param'  => 'owner',
        'short'  => 'O',
        'repeat' => false,
        'help'   => pht('Owner of the new task.'),
      ),
      'priority' => array(
        'param'  => 'priority',
        'short'  => 'P',
        'repeat' => false,
        'help'   => pht('Priority value (99, 90, 85, 80, 50, 25, 0)'),
      ),
      'project' => array(
        'param'  => 'project',
        'repeat' => true,
        'help'   => pht('Projects to assign to the task.'),
      ),
    );
  }

  public function run() {
    $summary = implode(' ', $this->getArgument('summary'));
    $ccs = $this->getArgument('cc');
    $owner = $this->getArgument('owner');
    $priority = $this->getArgument('priority');
    $slugs = $this->getArgument('project');

    $conduit = $this->getConduit();

    if (trim($summary) == '') {
      echo "Please provide a summary.\n";
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

    if ($owner) {
      $phids = array();
      $users = $conduit->callMethodSynchronous(
        'user.query',
        array(
          'usernames' => [$owner],
        ));
      foreach ($users as $user => $info) {
        $phids[] = $info['phid'];
      }
      $args['ownerPHID'] = $phids[0];
    }

    if ($priority !== null) {
      $args['priority'] = $priority;
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
          throw new ArcanistUsageException('No such project: "'.$slug.'"');
        }
        $phids[] = $project;
      }

      $args['projectPHIDs'] = $phids;
    }

    $result = $conduit->callMethodSynchronous('maniphest.createtask', $args);
    echo phutil_console_format(
      "Created task T%s: '<fg:green>**%s**</fg>' at <fg:blue>**%s**</fg>\n",
      $result['id'],
      $result['title'],
      $result['uri']);
  }

}
