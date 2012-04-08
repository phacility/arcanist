<?php

/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * Displays User Tasks
 *
 * @group workflow
 */
final class ArcanistTasksWorkflow extends ArcanistBaseWorkflow {

  private $tasks;

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
        'help' => "Show tasks that or open or closed, default is all.",
      ),
      'owner' => array(
        'param' => 'username',
        'paramtype' => 'username',
        'help' =>
          "Only show tasks assigned to the given username, ".
            "also accepts @all to show all, default is you.",
      ),
      'order' => array(
        'param' => 'task_order',
        'help' =>
          "Arrange tasks based on priority, created, or modified, ".
            "default is priority.",
      ),
      'limit' => array(
        'param' => 'n',
        'paramtype' => 'int',
        'help' => "Limit the amount of tasks outputted, default is all.",
      )
    );
  }

  public function run() {
    $output = array();

    $status = $this->getArgument('status');
    $owner = $this->getArgument('owner');
    $order = $this->getArgument('order');
    $limit = $this->getArgument('limit');
    $this->tasks = $this->loadManiphestTasks(
      ($status == 'all'?'any':$status),
      ($owner?$this->findOwnerPhid($owner):$this->getUserPHID()),
      $order,
      $limit
      );

    foreach ($this->tasks as $task) {
      $tid = "T{$task['id']}";
      switch ($task['priority']) {
        case 'Needs Triage':
          $color = 'magenta';
        break;
        case 'Unbreak Now!':
          $color = 'red';
        break;
        case 'High':
          $color = 'yellow';
        break;
        case 'Normal':
          $color = 'green';
        break;
        case 'Low':
          $color = 'blue';
        break;
        case 'Wishlist':
          $color = 'cyan';
        break;
        default:
          $color = 'white';
        break;
      }
      $task_priority = $task['priority'];
      $priority = phutil_console_format("<bg:{$color}> </bg>{$task_priority}");
      $output[] = array(phutil_console_format("**{$tid}**"),
                        $task['title'],
                        $priority,
                        ($task['status']?
                          phutil_console_format("<bg:red> </bg>Closed"):
                          phutil_console_format('<bg:green> </bg>Open'))
                );
    }
    $this->render($output);
  }

  private function render($table) {
    $column_length = array();
    foreach ($table as $row) {
      foreach ($row as $col => $cell) {
        if (!isset($column_length[$col]))
          $column_length[$col] = 0;
        if (strlen($cell) > $column_length[$col])
          $column_length[$col] = strlen($cell);
      }
    }
    foreach ($table as $row) {
      foreach ($row as $col => $cell) {
        echo $cell.str_repeat(' ', $column_length[$col] - strlen($cell) + 4);
      }
      echo "\n";
    }
  }

  private function findOwnerPhid($owner) {
    $conduit = $this->getConduit();
    $owner_phid = $conduit->callMethodSynchronous(
      'user.find',
      array(
        'aliases' => array($owner),
      ));
    return (isset($owner_phid[$owner])?$owner_phid[$owner]:false);
  }

  private function loadManiphestTasks($status, $owner_phid, $order, $limit) {
    $conduit = $this->getConduit();

    $find_params = array();
    if ($owner_phid !== false) {
      $find_params['ownerPHIDs'] = array($owner_phid);
    }
    if ($limit !== false) {
      $find_params['limit'] = $limit;
    }
    $find_params['order'] = ($order?"order-".$order:"order-priority");
    $find_params['status'] = ($status?"status-".$status:"status-open");

    $tasks = $conduit->callMethodSynchronous(
      'maniphest.find',
      $find_params
      );
    return $tasks;
  }

}
