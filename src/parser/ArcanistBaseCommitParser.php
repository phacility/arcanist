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

final class ArcanistBaseCommitParser {

  private $api;
  private $try;
  private $verbose = false;

  public function __construct(ArcanistRepositoryAPI $api) {
    $this->api = $api;
    return $this;
  }

  private function tokenizeBaseCommitSpecification($raw_spec) {
    if (!$raw_spec) {
      return array();
    }

    $spec = preg_split('/\s*,\s*/', $raw_spec);
    $spec = array_filter($spec);

    foreach ($spec as $rule) {
      if (strpos($rule, ':') === false) {
        throw new ArcanistUsageException(
          "Rule '{$rule}' is invalid, it must have a type and name like ".
          "'arc:upstream'.");
      }
    }

    return $spec;
  }

  private function log($message) {
    if ($this->verbose) {
      file_put_contents('php://stderr', $message."\n");
    }
  }

  public function resolveBaseCommit(array $specs) {
    $specs += array(
      'args'    => '',
      'local'   => '',
      'project' => '',
      'global'  => '',
      'system'  => '',
    );

    foreach ($specs as $source => $spec) {
      $specs[$source] = self::tokenizeBaseCommitSpecification($spec);
    }

    $this->try = array(
      'args',
      'local',
      'project',
      'global',
      'system',
    );

    while ($this->try) {
      $source = head($this->try);

      if (!idx($specs, $source)) {
        $this->log("No rules left from source '{$source}'.");
        array_shift($this->try);
        continue;
      }

      $this->log("Trying rules from source '{$source}'.");

      $rules = &$specs[$source];
      while ($rule = array_shift($rules)) {
        $this->log("Trying rule '{$rule}'.");

        $commit = $this->resolveRule($rule, $source);

        if ($commit === false) {
          // If a rule returns false, it means to go to the next ruleset.
          break;
        } else if ($commit !== null) {
          $this->log("Resolved commit '{$commit}' from rule '{$rule}'.");
          return $commit;
        }
      }
    }

    return null;
  }

  /**
   * Handle resolving individual rules.
   */
  private function resolveRule($rule, $source) {

    // NOTE: Returning `null` from this method means "no match".
    // Returning `false` from this method means "stop current ruleset".

    list($type, $name) = explode(':', $rule, 2);
    switch ($type) {
      case 'literal':
        return $name;
      case 'git':
      case 'hg':
        return $this->api->resolveBaseCommitRule($rule, $source);
      case 'arc':
        return $this->resolveArcRule($rule, $name, $source);
      default:
        throw new ArcanistUsageException(
          "Base commit rule '{$rule}' (from source '{$source}') ".
          "is not a recognized rule.");
    }
  }


  /**
   * Handle resolving "arc:*" rules.
   */
  private function resolveArcRule($rule, $name, $source) {
    switch ($name) {
      case 'verbose':
        $this->verbose = true;
        $this->log("Enabled verbose mode.");
        break;
      case 'prompt':
        $reason = "it is what you typed when prompted.";
        $this->api->setBaseCommitExplanation($reason);
        return phutil_console_prompt('Against which commit?');
      case 'local':
      case 'global':
      case 'project':
      case 'args':
      case 'system':
        // Push the other source on top of the list.
        array_unshift($this->try, $name);
        $this->log("Switching to source '{$name}'.");
        return false;
      case 'yield':
        // Cycle this source to the end of the list.
        $this->try[] = array_shift($this->try);
        $this->log("Yielding processing of rules from '{$source}'.");
        return false;
      case 'halt':
        // Dump the whole stack.
        $this->try = array();
        $this->log("Halting all rule processing.");
        return false;
      case 'skip':
        return null;
      case 'empty':
      case 'upstream':
      case 'outgoing':
      case 'bookmark':
        return $this->api->resolveBaseCommitRule($rule, $source);
      default:
        $matches = null;
        if (preg_match('/^exec\((.*)\)$/', $name, $matches)) {
          $root = $this->api->getWorkingCopyIdentity()->getProjectRoot();
          $future = new ExecFuture($matches[1]);
          $future->setCWD($root);
          list($err, $stdout) = $future->resolve();
          if (!$err) {
            return trim($stdout);
          } else {
            return null;
          }
        }

        throw new ArcanistUsageException(
          "Base commit rule '{$rule}' (from source '{$source}') ".
          "is not a recognized rule.");
    }
  }

}
