<?php

/*
 * Copyright 2011 Facebook, Inc.
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

class ArcanistConfiguration {

  public function buildWorkflow($command) {
    if ($command == '--help') {
      // Special-case "arc --help" to behave like "arc help" instead of telling
      // you to type "arc help" without being helpful.
      $command = 'help';
    }

    if ($command == 'base') {
      return null;
    }

    if (!phutil_module_exists('arcanist', 'workflow/'.$command)) {
      return null;
    }

    $workflow_class = 'Arcanist'.ucfirst($command).'Workflow';

    $workflow_class = preg_replace_callback(
      '/-([a-z])/',
      array(
        'ArcanistConfiguration',
        'replaceClassnameHyphens',
      ),
      $workflow_class);

    phutil_autoload_class($workflow_class);

    return newv($workflow_class, array());
  }

  public function buildAllWorkflows() {
    $classes = phutil_find_class_descendants('ArcanistBaseWorkflow');
    $workflows = array();
    foreach ($classes as $class) {
      $name = preg_replace('/^Arcanist(\w+)Workflow$/', '\1', $class);
      $name = strtolower($name);
      phutil_autoload_class($class);
      $workflows[$name] = newv($class, array());
    }

    return $workflows;
  }

  public function willRunWorkflow($command, ArcanistBaseWorkflow $workflow) {
    // This is a hook.
  }

  public function didRunWorkflow($command, ArcanistBaseWorkflow $workflow) {
    // This is a hook.
  }

  public function getCustomArgumentsForCommand($command) {
    return array();
  }

  public static function replaceClassnameHyphens($m) {
    return strtoupper($m[1]);
  }

}
