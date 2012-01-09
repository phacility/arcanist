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
 * Powers shell-completion scripts.
 *
 * @group workflow
 */
class ArcanistShellCompleteWorkflow extends ArcanistBaseWorkflow {

  public function getCommandHelp() {
    return phutil_console_format(<<<EOTEXT
      **shell-complete** __--current__ __N__ -- [__argv__]
          Supports: bash, etc.
          Implements shell completion. To use shell completion, source the
          appropriate script from 'resources/shell/' in your .shellrc.
EOTEXT
      );
  }

  public function getArguments() {
    return array(
      'current' => array(
        'help' => 'Current term in the argument list being completed.',
        'param' => 'cursor_position',
      ),
      '*' => 'argv',
    );
  }

  public function shouldShellComplete() {
    return false;
  }

  public function run() {

    $pos  = $this->getArgument('current');
    $argv = $this->getArgument('argv', array());
    $argc = count($argv);
    if ($pos === null) {
      $pos = $argc - 1;
    }

    // Determine which revision control system the working copy uses, so we
    // can filter out commands and flags which aren't supported. If we can't
    // figure it out, just return all flags/commands.
    $vcs = null;

    // We have to build our own because if we requiresWorkingCopy() we'll throw
    // if we aren't in a .arcconfig directory. We probably still can't do much,
    // but commands can raise more detailed errors.
    $working_copy = ArcanistWorkingCopyIdentity::newFromPath(getcwd());
    if ($working_copy->getProjectRoot()) {
      $repository_api = ArcanistRepositoryAPI::newAPIFromWorkingCopyIdentity(
        $working_copy);
      $vcs = $repository_api->getSourceControlSystemName();
    }

    $arc_config = $this->getArcanistConfiguration();

    if ($pos == 1) {
      $workflows = $arc_config->buildAllWorkflows();

      $complete = array();
      foreach ($workflows as $name => $workflow) {
        if (!$workflow->shouldShellComplete()) {
          continue;
        }

        $supported = $workflow->getSupportedRevisionControlSystems();

        $ok = (in_array('any', $supported)) ||
              (in_array($vcs, $supported));
        if (!$ok) {
          continue;
        }

        $complete[] = $name;
      }

      echo implode(' ', $complete)."\n";
      return 0;
    } else {
      $workflow = $arc_config->buildWorkflow($argv[1]);
      if (!$workflow) {
        return 1;
      }
      $arguments = $workflow->getArguments();

      $prev = idx($argv, $pos - 1, null);
      if (!strncmp($prev, '--', 2)) {
        $prev = substr($prev, 2);
      } else {
        $prev = null;
      }

      if ($prev !== null &&
          isset($arguments[$prev]) &&
          isset($arguments[$prev]['param'])) {

        $type = idx($arguments[$prev], 'paramtype');
        switch ($type) {
          case 'file':
            echo "FILE\n";
            break;
          case 'complete':
            echo implode(' ', $workflow->getShellCompletions($argv))."\n";
            break;
          default:
            echo "ARGUMENT\n";
            break;
        }
        return 0;
      } else {

        $output = array();
        foreach ($arguments as $argument => $spec) {
          if ($argument == '*') {
            continue;
          }
          if ($vcs &&
              isset($spec['supports']) &&
              !in_array($vcs, $spec['supports'])) {
            continue;
          }
          $output[] = '--'.$argument;
        }

        $cur = idx($argv, $pos, '');
        $any_match = false;
        foreach ($output as $possible) {
          if (!strncmp($possible, $cur, strlen($cur))) {
            $any_match = true;
          }
        }

        if (!$any_match && isset($arguments['*'])) {
          // TODO: the '*' specifier should probably have more details about
          // whether or not it is a list of files. Since it almost always is in
          // practice, assume FILE for now.
          echo "FILE\n";
        } else {
          echo implode(' ', $output)."\n";
        }
        return 0;
      }
    }
  }
}
