<?php

final class ArcanistConfigurationDrivenLintEngine extends ArcanistLintEngine {

  private $debugMode;

  public function buildLinters() {
    $working_copy = $this->getWorkingCopy();
    $config_path = $working_copy->getProjectPath('.arclint');

    if (!Filesystem::pathExists($config_path)) {
      throw new Exception(
        "Unable to find '.arclint' file to configure linters. Create a ".
        "'.arclint' file in the root directory of the working copy.");
    }

    $data = Filesystem::readFile($config_path);
    $config = json_decode($data, true);
    if (!is_array($config)) {
      throw new Exception(
        "Expected '.arclint' file to be a valid JSON file, but failed to ".
        "decode it: {$config_path}");
    }

    $linters = $this->loadAvailableLinters();

    PhutilTypeSpec::checkMap(
      $config,
      array(
        'linters' => 'map<string, map<string, wild>>',
        'debug'   => 'optional bool',
      ));

    $this->debugMode = idx($config, 'debug', false);

    $built_linters = array();
    $all_paths = $this->getPaths();
    foreach ($config['linters'] as $name => $spec) {
      $type = idx($spec, 'type');
      if ($type !== null) {
        if (empty($linters[$type])) {
          $list = implode(', ', array_keys($linters));
          throw new Exception(
            "Linter '{$name}' specifies invalid type '{$type}'. Available ".
            "linters are: {$list}.");
        }

        $linter = clone $linters[$type];
        $linter->setEngine($this);
        $more = $linter->getLinterConfigurationOptions();
      } else {
        // We'll raise an error below about the invalid "type" key.
        $linter = null;
        $more = array();
      }

      PhutilTypeSpec::checkMap(
        $spec,
        array(
          'type' => 'string',
          'include' => 'optional string | list<string>',
          'exclude' => 'optional string | list<string>',
        ) + $more);

      foreach ($more as $key => $value) {
        if (array_key_exists($key, $spec)) {
          try {
            $linter->setLinterConfigurationValue($key, $spec[$key]);
          } catch (Exception $ex) {
            $message = pht(
              'Error in parsing ".arclint" file, in key "%s" for '.
              'linter "%s": %s',
              $key,
              $name,
              $ex->getMessage());
            throw new PhutilProxyException($message, $ex);
          }
        }
      }

      $include = (array)idx($spec, 'include', array());
      $exclude = (array)idx($spec, 'exclude', array());

      $this->validateRegexps($include, $name, 'include');
      $this->validateRegexps($exclude, $name, 'exclude');

      $this->debugLog('Examining paths for linter "%s".', $name);
      $paths = $this->matchPaths($all_paths, $include, $exclude);
      $this->debugLog(
        'Found %d matching paths for linter "%s".',
        count($paths),
        $name);

      $linter->setPaths($paths);


      $built_linters[] = $linter;
    }

    return $built_linters;
  }

  private function loadAvailableLinters() {
    $linters = id(new PhutilSymbolLoader())
      ->setAncestorClass('ArcanistLinter')
      ->loadObjects();

    $map = array();
    foreach ($linters as $linter) {
      $name = $linter->getLinterConfigurationName();

      // This linter isn't selectable through configuration.
      if ($name === null) {
        continue;
      }

      if (empty($map[$name])) {
        $map[$name] = $linter;
        continue;
      }

      $orig_class = get_class($map[$name]);
      $this_class = get_class($linter);
      throw new Exception(
        "Two linters ({$orig_class}, {$this_class}) both have the same ".
        "configuration name ({$name}). Linters must have unique configuration ".
        "names.");
    }

    return $map;
  }

  private function matchPaths(array $paths, array $include, array $exclude) {
    $debug = $this->debugMode;

    $match = array();
    foreach ($paths as $path) {
      $this->debugLog("Examining path '%s'...", $path);

      $keep = false;
      if (!$include) {
        $keep = true;
        $this->debugLog(
          "  Including path by default because there is no 'include' rule.");
      } else {
        $this->debugLog('  Testing "include" rules.');
        foreach ($include as $rule) {
          if (preg_match($rule, $path)) {
            $keep = true;
            $this->debugLog('  Path matches include rule: %s', $rule);
            break;
          } else {
            $this->debugLog('  Path does not match include rule: %s', $rule);
          }
        }
      }

      if (!$keep) {
        $this->debugLog('  Path does not match any include rules, discarding.');
        continue;
      }

      if ($exclude) {
        $this->debugLog('  Testing "exclude" rules.');
        foreach ($exclude as $rule) {
          if (preg_match($rule, $path)) {
            $this->debugLog('  Path matches "exclude" rule: %s', $rule);
            continue 2;
          } else {
            $this->debugLog('  Path does not match "exclude" rule: %s', $rule);
          }
        }
      }

      $this->debugLog('  Path matches.');
      $match[] = $path;
    }

    return $match;
  }

  private function validateRegexps(array $regexps, $linter, $config) {
    foreach ($regexps as $regexp) {
      $ok = @preg_match($regexp, '');
      if ($ok === false) {
        throw new Exception(
          pht(
            'Regular expression "%s" (in "%s" configuration for linter "%s") '.
            'is not a valid regular expression.',
            $regexp,
            $config,
            $linter));
      }
    }
  }

  private function debugLog($pattern /* , $arg, ... */) {
    if (!$this->debugMode) {
      return;
    }

    $console = PhutilConsole::getConsole();
    $argv = func_get_args();
    $argv[0] .= "\n";
    call_user_func_array(array($console, 'writeErr'), $argv);
  }


}
