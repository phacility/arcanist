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
 * Runtime workflow configuration. In Arcanist, commands you type like
 * "arc diff" or "arc lint" are called "workflows". This class allows you to add
 * new workflows (and extend existing workflows) by subclassing it and then
 * pointing to your subclass in your project configuration.
 *
 * When specified as the **arcanist_configuration** class in your project's
 * ##.arcconfig##, your subclass will be instantiated (instead of this class)
 * and be able to handle all the method calls. In particular, you can:
 *
 *    - create, replace, or disable workflows by overriding buildWorkflow()
 *      and buildAllWorkflows();
 *    - add additional steps before or after workflows run by overriding
 *      willRunWorkflow() or didRunWorkflow() or didAbortWorkflow(); and
 *    - add new flags to existing workflows by overriding
 *      getCustomArgumentsForCommand().
 *
 * @group config
 * @concrete-extensible
 */
class ArcanistConfiguration {

  public function buildWorkflow($command) {
    if ($command == '--help') {
      // Special-case "arc --help" to behave like "arc help" instead of telling
      // you to type "arc help" without being helpful.
      $command = 'help';
    }

    return idx($this->buildAllWorkflows(), $command);
  }

  public function buildAllWorkflows() {
    $symbols = id(new PhutilSymbolLoader())
      ->setType('class')
      ->setAncestorClass('ArcanistBaseWorkflow')
      ->selectAndLoadSymbols();

    $workflows = array();
    foreach ($symbols as $symbol) {
      $class = $symbol['name'];
      $workflow = newv($class, array());
      $name = $workflow->getWorkflowName();

      if (isset($workflows[$name])) {
        $other = get_class($workflows[$name]);
        throw new Exception(
          "Workflows {$class} and {$other} both implement workflows named ".
          "{$name}.");
      }

      $workflows[$workflow->getWorkflowName()] = $workflow;
    }

    return $workflows;
  }

  final public function isValidWorkflow($workflow) {
    return (bool)$this->buildWorkflow($workflow);
  }

  public function willRunWorkflow($command, ArcanistBaseWorkflow $workflow) {
    // This is a hook.
  }

  public function didRunWorkflow($command, ArcanistBaseWorkflow $workflow,
                                 $err) {
    // This is a hook.
  }

  public function didAbortWorkflow($command, $workflow, Exception $ex) {
    // This is a hook.
  }

  public function getCustomArgumentsForCommand($command) {
    return array();
  }

}
