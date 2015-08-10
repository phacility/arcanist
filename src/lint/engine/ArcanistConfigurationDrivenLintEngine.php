<?php

final class ArcanistConfigurationDrivenLintEngine extends ArcanistLintEngine {

  public function buildLinters() {
    $working_copy = $this->getWorkingCopy();
    $config_path = $working_copy->getProjectPath('.arclint');

    if (!Filesystem::pathExists($config_path)) {
      throw new ArcanistUsageException(
        pht(
          "Unable to find '%s' file to configure linters. Create an ".
          "'%s' file in the root directory of the working copy.",
          '.arclint',
          '.arclint'));
    }

    $data = Filesystem::readFile($config_path);
    $config = null;
    try {
      $config = phutil_json_decode($data);
    } catch (PhutilJSONParserException $ex) {
      throw new PhutilProxyException(
        pht(
          "Expected '%s' file to be a valid JSON file, but ".
          "failed to decode '%s'.",
          '.arclint',
          $config_path),
        $ex);
    }

    $linters = $this->loadAvailableLinters();

    try {
      PhutilTypeSpec::checkMap(
        $config,
        array(
          'exclude' => 'optional regex | list<regex>',
          'linters' => 'map<string, map<string, wild>>',
        ));
    } catch (PhutilTypeCheckException $ex) {
      throw new PhutilProxyException(
        pht("Error in parsing '%s' file.", $config_path),
        $ex);
    }

    $global_exclude = (array)idx($config, 'exclude', array());

    $built_linters = array();
    $all_paths = $this->getPaths();
    foreach ($config['linters'] as $name => $spec) {
      $type = idx($spec, 'type');
      if ($type !== null) {
        if (empty($linters[$type])) {
          throw new ArcanistUsageException(
            pht(
              "Linter '%s' specifies invalid type '%s'. ".
              "Available linters are: %s.",
              $name,
              $type,
              implode(', ', array_keys($linters))));
        }

        $linter = clone $linters[$type];
        $linter->setEngine($this);
        $more = $linter->getLinterConfigurationOptions();

        foreach ($more as $key => $option_spec) {
          PhutilTypeSpec::checkMap(
            $option_spec,
            array(
              'type' => 'string',
              'help' => 'string',
            ));
          $more[$key] = $option_spec['type'];
        }
      } else {
        // We'll raise an error below about the invalid "type" key.
        $linter = null;
        $more = array();
      }

      try {
        PhutilTypeSpec::checkMap(
          $spec,
          array(
            'type' => 'string',
            'include' => 'optional regex | list<regex>',
            'exclude' => 'optional regex | list<regex>',
          ) + $more);
      } catch (PhutilTypeCheckException $ex) {
        throw new PhutilProxyException(
          pht(
            "Error in parsing '%s' file, for linter '%s'.",
            '.arclint',
            $name),
          $ex);
      }

      foreach ($more as $key => $value) {
        if (array_key_exists($key, $spec)) {
          try {
            $linter->setLinterConfigurationValue($key, $spec[$key]);
          } catch (Exception $ex) {
            throw new PhutilProxyException(
              pht(
                "Error in parsing '%s' file, in key '%s' for linter '%s'.",
                '.arclint',
                $key,
                $name),
              $ex);
          }
        }
      }

      $include = (array)idx($spec, 'include', array());
      $exclude = (array)idx($spec, 'exclude', array());

      $console = PhutilConsole::getConsole();
      $console->writeLog(
        "%s\n",
        pht("Examining paths for linter '%s'.", $name));
      $paths = $this->matchPaths(
        $all_paths,
        $include,
        $exclude,
        $global_exclude);
      $console->writeLog(
        "%s\n",
        pht("Found %d matching paths for linter '%s'.", count($paths), $name));

      $linter->setPaths($paths);
      $built_linters[] = $linter;
    }

    return $built_linters;
  }

  private function loadAvailableLinters() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass('ArcanistLinter')
      ->setUniqueMethod('getLinterConfigurationName', true)
      ->execute();
  }

  private function matchPaths(
    array $paths,
    array $include,
    array $exclude,
    array $global_exclude) {

    $console = PhutilConsole::getConsole();

    $match = array();
    foreach ($paths as $path) {
      $keep = false;
      if (!$include) {
        $keep = true;
      } else {
        foreach ($include as $rule) {
          if (preg_match($rule, $path)) {
            $keep = true;
            break;
          }
        }
      }

      if (!$keep) {
        continue;
      }

      if ($exclude) {
        foreach ($exclude as $rule) {
          if (preg_match($rule, $path)) {
            continue 2;
          }
        }
      }

      if ($global_exclude) {
        foreach ($global_exclude as $rule) {
          if (preg_match($rule, $path)) {
            continue 2;
          }
        }
      }

      $match[] = $path;
    }

    return $match;
  }

}
