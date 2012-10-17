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
    );
  }

  public function run() {
    $summary = implode(' ', $this->getArgument('summary'));
    $ccs = $this->getArgument('cc');
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
