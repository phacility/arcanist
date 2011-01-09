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

class ArcanistHelpWorkflow extends ArcanistBaseWorkflow {

  public function getCommandHelp() {
    return phutil_console_format(<<<EOTEXT
      **help** [__command__]
          Supports: english
          Shows this help. With __command__, shows help about a specific
          command.
EOTEXT
      );
  }

  public function getArguments() {
    return array(
      '*' => 'command',
    );
  }

  public function run() {

    $arc_config = $this->getArcanistConfiguration();
    $workflows = $arc_config->buildAllWorkflows();
    ksort($workflows);

    $target = null;
    if ($this->getArgument('command')) {
      $target = reset($this->getArgument('command'));
      if (empty($workflows[$target])) {
        throw new ArcanistUsageException(
          "Unrecognized command '{$target}'. Try 'arc help'.");
      }
    }

    $cmdref = array();
    foreach ($workflows as $command => $workflow) {
      if ($target && $target != $command) {
        continue;
      }
      $optref = array();
      $arguments = $workflow->getArguments();

      $config_arguments = $arc_config->getCustomArgumentsForCommand($command);

      // This juggling is to put the extension arguments after the normal
      // arguments, and make sure the normal arguments aren't overwritten.
      ksort($arguments);
      ksort($config_arguments);
      foreach ($config_arguments as $argument => $spec) {
        if (empty($arguments[$argument])) {
          $arguments[$argument] = $spec;
        }
      }

      foreach ($arguments as $argument => $spec) {
        if ($argument == '*') {
          continue;
        }
        if (isset($spec['param'])) {
          if (isset($spec['short'])) {
            $optref[] = phutil_console_format(
              "          __--%s__ __%s__, __-%s__ __%s__",
              $argument,
              $spec['param'],
              $spec['short'],
              $spec['param']);
          } else {
            $optref[] = phutil_console_format(
              "          __--%s__ __%s__",
              $argument,
              $spec['param']);
          }
        } else {
          if (isset($spec['short'])) {
            $optref[] = phutil_console_format(
              "          __--%s__, __-%s__",
              $argument,
              $spec['short']);
          } else {
            $optref[] = phutil_console_format(
              "          __--%s__",
              $argument);
          }
        }

        if (isset($config_arguments[$argument])) {
          $optref[] = "              (This is a custom option for this ".
                      "project.)";
        }

        if (isset($spec['supports'])) {
          $optref[] = "              Supports: ".
                      implode(', ', $spec['supports']);
        }

        if (isset($spec['help'])) {
          $docs = $spec['help'];
        } else {
          $docs = 'This option is not documented.';
        }
        $docs = phutil_console_wrap($docs, 14);
        $optref[] = "              {$docs}\n";
      }
      if ($optref) {
        $optref = implode("\n", $optref);
        $optref = "\n\n".$optref;
      } else {
        $optref = "\n";
      }

      $cmdref[] = $workflow->getCommandHelp().$optref;
    }
    $cmdref = implode("\n\n", $cmdref);

    if ($target) {
      echo "\n".$cmdref."\n";
      return;
    }

    $self = 'arc';
    echo phutil_console_format(<<<EOTEXT
**NAME**
      **{$self}** - arcanist, a code review and revision management utility

**SYNOPSIS**
      **{$self}** __command__ [__options__] [__args__]

      This help file provides a detailed command reference.

**COMMAND REFERENCE**

{$cmdref}

**OPTION REFERENCE**

      __--trace__
          Debugging command. Shows underlying commands as they are executed,
          and full stack traces when exceptions are thrown.

      __--no-ansi__
          Output in plain ASCII text only, without color or style.


EOTEXT
    );
  }
}
