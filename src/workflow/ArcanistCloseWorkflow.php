<?php

/**
 * Close a task.
 */
final class ArcanistCloseWorkflow extends ArcanistWorkflow {

  private $tasks;
  private $statusOptions;
  private $statusData;

  private function loadStatusData() {
    $this->statusData = $this->getConduit()->callMethodSynchronous(
      'maniphest.querystatuses',
      array());
    return $this;
  }

  private function getStatusOptions() {
    if ($this->statusData === null) {
      throw new PhutilInvalidStateException('loadStatusData');
    }
    return idx($this->statusData, 'statusMap');
  }
  private function getDefaultClosedStatus() {
    if ($this->statusData === null) {
      throw new PhutilInvalidStateException('loadStatusData');
    }
    return idx($this->statusData, 'defaultClosedStatus');
  }

  public function getWorkflowName() {
    return 'close';
  }

  public function getCommandSynopses() {
    return phutil_console_format(<<<EOTEXT
      **close** __task_id__ [__options__]
EOTEXT
      );
  }

  public function getCommandHelp() {
    return phutil_console_format(<<<EOTEXT
        Close a task or otherwise update its status.
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
      '*' => 'task_id',
      'message' => array(
        'short' => 'm',
        'param' => 'comment',
        'help'  => pht('Provide a comment with your status change.'),
      ),
      'status'  => array(
        'param' => 'status',
        'short' => 's',
        'help'  => pht(
          'Specify a new status. Valid status options can be '.
          'seen with the `%s` argument.',
          'list-statuses'),
      ),
      'list-statuses' => array(
        'help' => pht('Show available status options and exit.'),
      ),
    );
  }

  public function run() {
    $this->loadStatusData();
    $list_statuses = $this->getArgument('list-statuses');
    if ($list_statuses) {
      echo phutil_console_format(
        "%s\n",
        pht(
          "Valid status options are:\n\t%s",
          implode($this->getStatusOptions(), ', ')));
      return 0;
    }
    $ids = $this->getArgument('task_id');
    $message = $this->getArgument('message');
    $status = strtolower($this->getArgument('status'));

    $status_options = $this->getStatusOptions();
    if (!isset($status) || $status == '') {
      $status = $this->getDefaultClosedStatus();
    }

    if (!isset($status_options[$status])) {
      $options = array_keys($status_options);
      $last = array_pop($options);
      echo pht(
        "Invalid status %s, valid options are %s, or %s.\n",
        $status,
        implode(', ', $options),
        $last);

      return;
    }

    foreach ($ids as $id) {
      if (!preg_match('/^T?\d+$/', $id)) {
        echo pht('Invalid Task ID: %s.', $id)."\n";
        return 1;
      }
      $id = ltrim($id, 'T');
      $result = $this->closeTask($id, $status, $message);
      $current_status = $status_options[$status];
      if ($result) {
        echo pht(
          "%s's status is now set to %s.\n",
          "T{$id}",
          $current_status);
      } else {
        echo pht(
          "%s is already set to %s.\n",
          "T{$id}",
          $current_status);
      }
    }
    return 0;
  }

  private function closeTask($task_id, $status, $comment = '') {
    $conduit = $this->getConduit();
    $info = $conduit->callMethodSynchronous(
      'maniphest.info',
      array(
        'task_id' => $task_id,
      ));
    if ($info['status'] == $status) {
      return false;
    }
    return $conduit->callMethodSynchronous(
      'maniphest.update',
      array(
        'id' => $task_id,
        'status' => $status,
        'comments' => $comment,
      ));
  }

}
