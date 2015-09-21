<?php

/**
 * Displays User Tasks.
 */
final class ArcanistTasksWorkflow extends ArcanistWorkflow {

  private $tasks;

  public function getWorkflowName() {
    return 'tasks';
  }

  public function getCommandSynopses() {
    return phutil_console_format(<<<EOTEXT
      **tasks** [__options__]
EOTEXT
      );
  }

  public function getCommandHelp() {
    return phutil_console_format(<<<EOTEXT
        View all assigned tasks.
EOTEXT
      );
  }

  public function requiresConduit() {
    return true;
  }

  public function requiresRepositoryAPI() {
    return false;
  }

  public function requiresAuthentication() {
    return true;
  }

  public function getArguments() {
    return array(
      'status' => array(
        'param' => 'task_status',
        'help' => pht('Show tasks that are open or closed, default is open.'),
      ),
      'owner' => array(
        'param' => 'username',
        'paramtype' => 'username',
        'help' => pht(
          'Only show tasks assigned to the given username, '.
          'also accepts %s to show all, default is you.',
          '@all'),
        'conflict' => array(
          'unassigned' => pht('%s suppresses unassigned', '--owner'),
        ),
      ),
      'order' => array(
        'param' => 'task_order',
        'help' => pht(
          'Arrange tasks based on priority, created, or modified, '.
          'default is priority.'),
      ),
      'limit' => array(
        'param' => 'n',
        'paramtype' => 'int',
        'help' => pht('Limit the amount of tasks outputted, default is all.'),
      ),
      'unassigned' => array(
        'help' => pht('Only show tasks that are not assigned (upforgrabs).'),
      ),
    );
  }

  public function run() {
    $output = array();

    $status     = $this->getArgument('status');
    $owner      = $this->getArgument('owner');
    $order      = $this->getArgument('order');
    $limit      = $this->getArgument('limit');
    $unassigned = $this->getArgument('unassigned');

    if ($owner) {
      $owner_phid = $this->findOwnerPhid($owner);
    } else if ($unassigned) {
      $owner_phid = null;
    } else {
      $owner_phid = $this->getUserPHID();
    }

    $this->tasks = $this->loadManiphestTasks(
      ($status == 'all' ? 'any' : $status),
      $owner_phid,
      $order,
      $limit);

    if (!$this->tasks) {
      echo pht('No tasks found.')."\n";
      return 0;
    }

    $table = id(new PhutilConsoleTable())
      ->setShowHeader(false)
      ->addColumn('id', array('title' => pht('ID')))
      ->addColumn('title', array('title' => pht('Title')))
      ->addColumn('priority', array('title' => pht('Priority')))
      ->addColumn('status', array('title' => pht('Status')));

    foreach ($this->tasks as $task) {
      $output = array();

      // Render the "T123" column.
      $task_id = 'T'.$task['id'];
      $formatted_task_id = tsprintf('**%s**', $task_id);
      $output['id'] = $formatted_task_id;

      // Render the "Title" column.
      $formatted_title = rtrim($task['title']);
      $output['title'] = $formatted_title;

      // Render the "Priority" column.
      $web_to_terminal_colors = array(
        'violet'      => 'magenta',
        'indigo'      => 'magenta',
        'orange'      => 'red',
        'sky'         => 'cyan',
        'red'         => 'red',
        'yellow'      => 'yellow',
        'green'       => 'green',
        'blue'        => 'blue',
        'cyan'        => 'cyan',
        'magenta'     => 'magenta',
        'lightred'    => 'red',
        'lightorange' => 'red',
        'lightyellow' => 'yellow',
        'lightgreen'  => 'green',
        'lightblue'   => 'blue',
        'lightsky'    => 'blue',
        'lightindigo' => 'magenta',
        'lightviolet' => 'magenta',
      );

      if (isset($task['priorityColor'])) {
        $color = idx($web_to_terminal_colors, $task['priorityColor'], 'white');
      } else {
        $color = 'white';
      }
      $formatted_priority = tsprintf(
        "<bg:{$color}> </bg> %s",
        $task['priority']);
      $output['priority'] = $formatted_priority;

      // Render the "Status" column.
      if (isset($task['isClosed'])) {
        if ($task['isClosed']) {
          $status_text = $task['statusName'];
          $status_color = 'red';
        } else {
          $status_text = $task['statusName'];
          $status_color = 'green';
        }
        $formatted_status = tsprintf(
          "<bg:{$status_color}> </bg> %s",
          $status_text);
        $output['status'] = $formatted_status;
      } else {
        $output['status'] = '';
      }

      $table->addRow($output);
    }

    $table->draw();
  }

  private function findOwnerPHID($owner) {
    $conduit = $this->getConduit();

    $users = $conduit->callMethodSynchronous(
      'user.query',
      array(
        'usernames' => array($owner),
      ));

    if (!$users) {
      return null;
    }

    $user = head($users);
    return idx($user, 'phid');
  }

  private function loadManiphestTasks($status, $owner_phid, $order, $limit) {
    $conduit = $this->getConduit();

    $find_params = array();
    if ($owner_phid !== null) {
      $find_params['ownerPHIDs'] = array($owner_phid);
    }

    if ($limit !== false) {
      $find_params['limit'] = $limit;
    }

    $find_params['order'] = ($order ? 'order-'.$order : 'order-priority');
    $find_params['status'] = ($status ? 'status-'.$status : 'status-open');

    return $conduit->callMethodSynchronous('maniphest.query', $find_params);
  }

}
