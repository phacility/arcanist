<?php

final class ArcanistBaseCommitParser extends Phobject {

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
          pht(
            "Rule '%s' is invalid, it must have a type and name like '%s'.",
            $rule,
            'arc:upstream'));
      }
    }

    return $spec;
  }

  private function log($message) {
    if ($this->verbose) {
      fwrite(STDERR, $message."\n");
    }
  }

  public function resolveBaseCommit(array $specs) {
    $specs += array(
      'runtime' => '',
      'local'   => '',
      'project' => '',
      'user'    => '',
      'system'  => '',
    );

    foreach ($specs as $source => $spec) {
      $specs[$source] = self::tokenizeBaseCommitSpecification($spec);
    }

    $this->try = array(
      'runtime',
      'local',
      'project',
      'user',
      'system',
    );

    while ($this->try) {
      $source = head($this->try);

      if (!idx($specs, $source)) {
        $this->log(pht("No rules left from source '%s'.", $source));
        array_shift($this->try);
        continue;
      }

      $this->log(pht("Trying rules from source '%s'.", $source));

      $rules = &$specs[$source];
      while ($rule = array_shift($rules)) {
        $this->log(pht("Trying rule '%s'.", $rule));

        $commit = $this->resolveRule($rule, $source);

        if ($commit === false) {
          // If a rule returns false, it means to go to the next ruleset.
          break;
        } else if ($commit !== null) {
          $this->log(pht(
            "Resolved commit '%s' from rule '%s'.",
            $commit,
            $rule));
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
          pht(
            "Base commit rule '%s' (from source '%s') ".
            "is not a recognized rule.",
            $rule,
            $source));
    }
  }


  /**
   * Handle resolving "arc:*" rules.
   */
  private function resolveArcRule($rule, $name, $source) {
    $name = $this->updateLegacyRuleName($name);

    switch ($name) {
      case 'verbose':
        $this->verbose = true;
        $this->log(pht('Enabled verbose mode.'));
        break;
      case 'prompt':
        $reason = pht('it is what you typed when prompted.');
        $this->api->setBaseCommitExplanation($reason);
        return phutil_console_prompt(pht('Against which commit?'));
      case 'local':
      case 'user':
      case 'project':
      case 'runtime':
      case 'system':
        // Push the other source on top of the list.
        array_unshift($this->try, $name);
        $this->log(pht("Switching to source '%s'.", $name));
        return false;
      case 'yield':
        // Cycle this source to the end of the list.
        $this->try[] = array_shift($this->try);
        $this->log(pht("Yielding processing of rules from '%s'.", $source));
        return false;
      case 'halt':
        // Dump the whole stack.
        $this->try = array();
        $this->log(pht('Halting all rule processing.'));
        return false;
      case 'skip':
        return null;
      case 'empty':
      case 'upstream':
      case 'outgoing':
      case 'bookmark':
      case 'amended':
      case 'this':
        return $this->api->resolveBaseCommitRule($rule, $source);
      default:
        $matches = null;
        if (preg_match('/^exec\((.*)\)$/', $name, $matches)) {
          $root = $this->api->getWorkingCopyIdentity()->getProjectRoot();
          $future = new ExecFuture('%C', $matches[1]);
          $future->setCWD($root);
          list($err, $stdout) = $future->resolve();
          if (!$err) {
            return trim($stdout);
          } else {
            return null;
          }
        } else if (preg_match('/^nodiff\((.*)\)$/', $name, $matches)) {
          return $this->api->resolveBaseCommitRule($rule, $source);
        }

        throw new ArcanistUsageException(
          pht(
            "Base commit rule '%s' (from source '%s') ".
            "is not a recognized rule.",
            $rule,
            $source));
    }
  }

  private function updateLegacyRuleName($name) {
    $updated = array(
      'global' => 'user',
      'args'   => 'runtime',
    );
    $new_name = idx($updated, $name);
    if ($new_name) {
      $this->log(pht("Translating legacy name '%s' to '%s'", $name, $new_name));
      return $new_name;
    }
    return $name;
  }

}
