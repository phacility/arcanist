<?php

final class ArcanistConfigurationDrivenUnitTestEngine
  extends ArcanistUnitTestEngine {

  protected function supportsRunAllTests() {
    $engines = $this->buildTestEngines();

    foreach ($engines as $engine) {
      if ($engine->supportsRunAllTests()) {
        return true;
      }
    }

    return false;
  }

  public function buildTestEngines() {
    $working_copy = $this->getWorkingCopy();
    $config_path  = $working_copy->getProjectPath('.arcunit');

    if (!Filesystem::pathExists($config_path)) {
      throw new ArcanistUsageException(
        pht(
          "Unable to find '%s' file to configure test engines. Create an ".
          "'%s' file in the root directory of the working copy.",
          '.arcunit',
          '.arcunit'));
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
          '.arcunit',
          $config_path),
        $ex);
    }

    $test_engines = $this->loadAvailableTestEngines();

    try {
      PhutilTypeSpec::checkMap(
        $config,
        array(
          'engines' => 'map<string, map<string, wild>>',
        ));
    } catch (PhutilTypeCheckException $ex) {
      throw new PhutilProxyException(
        pht("Error in parsing '%s' file.", $config_path),
        $ex);
    }

    $built_test_engines = array();
    $all_paths = $this->getPaths();

    foreach ($config['engines'] as $name => $spec) {
      $type = idx($spec, 'type');

      if ($type !== null) {
        if (empty($test_engines[$type])) {
          throw new ArcanistUsageException(
            pht(
              "Test engine '%s' specifies invalid type '%s'. ".
              "Available test engines are: %s.",
              $name,
              $type,
              implode(', ', array_keys($test_engines))));
        }

        $test_engine = clone $test_engines[$type];
      } else {
        // We'll raise an error below about the invalid "type" key.
        // TODO: Can we just do the type check first, and simplify this a bit?
        $test_engine = null;
      }

      try {
        PhutilTypeSpec::checkMap(
          $spec,
          array(
            'type' => 'string',
            'include' => 'optional regex | list<regex>',
            'exclude' => 'optional regex | list<regex>',
          ));
      } catch (PhutilTypeCheckException $ex) {
        throw new PhutilProxyException(
          pht(
            "Error in parsing '%s' file, for test engine '%s'.",
            '.arcunit',
            $name),
          $ex);
      }

      if ($all_paths) {
        $include = (array)idx($spec, 'include', array());
        $exclude = (array)idx($spec, 'exclude', array());
        $paths = $this->matchPaths(
          $all_paths,
          $include,
          $exclude);

        $test_engine->setPaths($paths);
      }

      $built_test_engines[] = $test_engine;
    }

    return $built_test_engines;
  }

  public function run() {
    $renderer = $this->renderer;
    $this->setRenderer(null);

    $paths = $this->getPaths();

    // If we are running with `--everything` then `$paths` will be `null`.
    if (!$paths) {
      $paths = array();
    }

    $engines     = $this->buildTestEngines();
    $all_results = array();
    $exceptions  = array();

    foreach ($engines as $engine) {
      $engine
        ->setWorkingCopy($this->getWorkingCopy())
        ->setEnableCoverage($this->getEnableCoverage())
        ->setRenderer($renderer);

      // TODO: At some point, maybe we should emit a warning here if an engine
      // doesn't support `--everything`, to reduce surprise when `--everything`
      // does not really mean `--everything`.
      if ($engine->supportsRunAllTests()) {
        $engine->setRunAllTests($this->getRunAllTests());
      }

      try {
        // TODO: Type check the results.
        $results = $engine->run();
        $all_results[] = $results;

        foreach ($results as $result) {
          if ($engine->shouldEchoTestResults()) {
            echo $renderer->renderUnitResult($result);
          }
        }
      } catch (ArcanistNoEffectException $ex) {
        $exceptions[] = $ex;
      }
    }

    if (!$all_results) {
      // If all engines throw an `ArcanistNoEffectException`, then we should
      // preserve this behavior.
      throw new ArcanistNoEffectException(pht('No tests to run.'));
    }

    return array_mergev($all_results);
  }

  public function shouldEchoTestResults() {
    return false;
  }

  private function loadAvailableTestEngines() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass('ArcanistUnitTestEngine')
      ->setUniqueMethod('getEngineConfigurationName', true)
      ->execute();
  }

  /**
   * TODO: This is copied from @{class:ArcanistConfigurationDrivenLintEngine}.
   */
  private function matchPaths(array $paths, array $include, array $exclude) {
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

      $match[] = $path;
    }

    return $match;
  }

}
