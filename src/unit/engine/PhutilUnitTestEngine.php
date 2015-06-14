<?php

/**
 * Very basic unit test engine which runs libphutil tests.
 */
final class PhutilUnitTestEngine extends ArcanistUnitTestEngine {

  protected function supportsRunAllTests() {
    return true;
  }

  public function run() {
    if ($this->getRunAllTests()) {
      $run_tests = $this->getAllTests();
    } else {
      $run_tests = $this->getTestsForPaths();
    }

    if (!$run_tests) {
      throw new ArcanistNoEffectException(pht('No tests to run.'));
    }

    $enable_coverage = $this->getEnableCoverage();

    if ($enable_coverage !== false) {
      if (!function_exists('xdebug_start_code_coverage')) {
        if ($enable_coverage === true) {
          throw new ArcanistUsageException(
            pht(
              'You specified %s but %s is not available, so '.
              'coverage can not be enabled for %s.',
              '--coverage',
              'XDebug',
              __CLASS__));
        }
      } else {
        $enable_coverage = true;
      }
    }

    $test_cases = array();

    foreach ($run_tests as $test_class) {
      $test_case = newv($test_class, array())
        ->setEnableCoverage($enable_coverage)
        ->setWorkingCopy($this->getWorkingCopy());

      if ($this->getPaths()) {
        $test_case->setPaths($this->getPaths());
      }

      if ($this->renderer) {
        $test_case->setRenderer($this->renderer);
      }

      $test_cases[] = $test_case;
    }

    foreach ($test_cases as $test_case) {
      $test_case->willRunTestCases($test_cases);
    }

    $results = array();
    foreach ($test_cases as $test_case) {
      $results[] = $test_case->run();
    }
    $results = array_mergev($results);

    foreach ($test_cases as $test_case) {
      $test_case->didRunTestCases($test_cases);
    }

    return $results;
  }

  private function getAllTests() {
    $project_root = $this->getWorkingCopy()->getProjectRoot();

    $symbols = id(new PhutilSymbolLoader())
      ->setType('class')
      ->setAncestorClass('PhutilTestCase')
      ->setConcreteOnly(true)
      ->selectSymbolsWithoutLoading();

    $in_working_copy = array();

    $run_tests = array();
    foreach ($symbols as $symbol) {
      if (!preg_match('@(?:^|/)__tests__/@', $symbol['where'])) {
        continue;
      }

      $library = $symbol['library'];

      if (!isset($in_working_copy[$library])) {
        $library_root = phutil_get_library_root($library);
        $in_working_copy[$library] = Filesystem::isDescendant(
          $library_root,
          $project_root);
      }

      if ($in_working_copy[$library]) {
        $run_tests[] = $symbol['name'];
      }
    }

    return $run_tests;
  }

  /**
   * Retrieve all relevant test cases.
   *
   * Looks for any class that extends @{class:PhutilTestCase} inside a
   * `__tests__` directory in any parent directory of every affected file.
   *
   * The idea is that "infrastructure/__tests__/" tests defines general tests
   * for all of "infrastructure/", and those tests run for any change in
   * "infrastructure/". However, "infrastructure/concrete/rebar/__tests__/"
   * defines more specific tests that run only when "rebar/" (or some
   * subdirectory) changes.
   *
   * @return list<string>  The names of the test case classes to be executed.
   */
  private function getTestsForPaths() {
    $look_here = $this->getTestPaths();
    $run_tests = array();

    foreach ($look_here as $path_info) {
      $library = $path_info['library'];
      $path    = $path_info['path'];

      $symbols = id(new PhutilSymbolLoader())
        ->setType('class')
        ->setLibrary($library)
        ->setPathPrefix($path)
        ->setAncestorClass('PhutilTestCase')
        ->setConcreteOnly(true)
        ->selectAndLoadSymbols();

      foreach ($symbols as $symbol) {
        $run_tests[$symbol['name']] = true;
      }
    }

    return array_keys($run_tests);
  }

  /**
   * Returns the paths in which we should look for tests to execute.
   *
   * @return list<string>  A list of paths in which to search for test cases.
   */
  public function getTestPaths() {
    $root  = $this->getWorkingCopy()->getProjectRoot();
    $paths = array();

    foreach ($this->getPaths() as $path) {
      $library_root = phutil_get_library_root_for_path($path);

      if (!$library_root) {
        continue;
      }

      $library_name = phutil_get_library_name_for_root($library_root);

      if (!$library_name) {
        throw new Exception(
          pht(
            "Attempting to run unit tests on a libphutil library which has ".
            "not been loaded, at:\n\n".
            "    %s\n\n".
            "This probably means one of two things:\n\n".
            "    - You may need to add this library to %s.\n".
            "    - You may be running tests on a copy of libphutil or ".
            "arcanist using a different copy of libphutil or arcanist. ".
            "This operation is not supported.\n",
            $library_root,
            '.arcconfig.'));
      }

      $path = Filesystem::resolvePath($path, $root);
      $library_path = Filesystem::readablePath($path, $library_root);

      if (!Filesystem::isDescendant($path, $library_root)) {
        // We have encountered some kind of symlink maze -- for instance, $path
        // is some symlink living outside the library that links into some file
        // inside the library. Just ignore these cases, since the affected file
        // does not actually lie within the library.
        continue;
      }

      if (is_file($path) && preg_match('@(?:^|/)__tests__/@', $path)) {
        $paths[$library_name.':'.$library_path] = array(
          'library' => $library_name,
          'path'    => $library_path,
        );
        continue;
      }

      foreach (Filesystem::walkToRoot($path, $library_root) as $subpath) {
        if ($subpath == $library_root) {
          $paths[$library_name.':.'] = array(
            'library' => $library_name,
            'path'    => '__tests__/',
          );
        } else {
          $library_subpath = Filesystem::readablePath($subpath, $library_root);

          $paths[$library_name.':'.$library_subpath] = array(
            'library' => $library_name,
            'path'    => $library_subpath.'/__tests__/',
          );
        }
      }
    }

    return $paths;
  }

  public function shouldEchoTestResults() {
    return !$this->renderer;
  }

}
