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
 * Close a task
 *
 * @group workflow
 */
final class ArcanistCloseWorkflow extends ArcanistBaseWorkflow {

  private $tasks;
  private $statusOptions = array(
    "resolved"  => 1,
    "wontfix"   => 2,
    "invalid"   => 3,
    "duplicate" => 4,
    "spite"     => 5,
    "open"      => 0
    );


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
        Close a task.
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
    $options = array_keys($this->statusOptions);
    $last = array_pop($options);
    return array(
      '*' => 'task_id',
      'message' => array(
        'short' => 'm',
        'param' => 'comment',
        'help'  => "Provide a comment with your status change.",
      ),
      'status'  => array(
        'param' => 'status',
        'short' => 's',
        'help'  => "New status. Valid options are ".
          implode(', ', $options).", or {$last}. Default is resolved.\n"
      ),
    );
  }

  public function run() {
    $ids = $this->getArgument('task_id');
    $message = $this->getArgument('message');
    $status = strtolower($this->getArgument('status'));

    if (!isset($status) || $status == '') {
      $status = head_key($this->statusOptions);
    }

    if (isset($this->statusOptions[$status])) {
      $status = $this->statusOptions[$status];
    } else {
      $options = array_keys($this->statusOptions);
      $last = array_pop($options);
      echo "Invalid status {$status}, valid options are ".
        implode(', ', $options).", or {$last}.\n";
      return;
    }

    foreach ($ids as $id) {
      if (!preg_match("/^T?\d+$/", $id)) {
        echo "Invalid Task ID: {$id}.\n";
        return 1;
      }
      $id = ltrim($id, 'T');
      $result = $this->closeTask($id, $status, $message);
      $status_options = array_flip($this->statusOptions);
      $current_status = $status_options[$status];
      if ($result) {
        echo "T{$id}'s status is now set to {$current_status}.\n";
      } else {
        echo "T{$id} is already set to {$current_status}.\n";
      }
    }
    return 0;
  }

  private function closeTask($task_id, $status = 1, $comment = "") {
    $conduit = $this->getConduit();
    $info = $conduit->callMethodSynchronous(
      'maniphest.info',
      array(
        'task_id' => $task_id
      ));
    if ($info['status'] == $status) {
      return false;
    }
    return $conduit->callMethodSynchronous(
      'maniphest.update',
      array(
        'id' => $task_id,
        'status' => $status,
        'comments' => $comment
      ));
  }

}
