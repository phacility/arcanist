<?php

/**
 * Uses xUnit (http://xunit.codeplex.com/) to test C# code.
 *
 * Assumes that when modifying a file with a path like `SomeAssembly/MyFile.cs`,
 * that the test assembly that verifies the functionality of `SomeAssembly` is
 * located at `SomeAssembly.Tests`.
 *
 * @concrete-extensible
 */
class XUnitTestEngine extends ArcanistUnitTestEngine {

  protected $runtimeEngine;
  protected $buildEngine;
  protected $testEngine;
  protected $projectRoot;
  protected $xunitHintPath;
  protected $discoveryRules;

  /**
   * This test engine supports running all tests.
   */
  protected function supportsRunAllTests() {
    return true;
  }

  /**
   * Determines what executables and test paths to use. Between platforms this
   * also changes whether the test engine is run under .NET or Mono. It also
   * ensures that all of the required binaries are available for the tests to
   * run successfully.
   *
   * @return void
   */
  protected function loadEnvironment() {
    $this->projectRoot = $this->getWorkingCopy()->getProjectRoot();

    // Determine build engine.
    if (Filesystem::binaryExists('msbuild')) {
      $this->buildEngine = 'msbuild';
    } else if (Filesystem::binaryExists('xbuild')) {
      $this->buildEngine = 'xbuild';
    } else {
      throw new Exception(
        pht(
          'Unable to find %s or %s in %s!',
          'msbuild',
          'xbuild',
          'PATH'));
    }

    // Determine runtime engine (.NET or Mono).
    if (phutil_is_windows()) {
      $this->runtimeEngine = '';
    } else if (Filesystem::binaryExists('mono')) {
      $this->runtimeEngine = Filesystem::resolveBinary('mono');
    } else {
      throw new Exception(
        pht('Unable to find Mono and you are not on Windows!'));
    }

    // Read the discovery rules.
    $this->discoveryRules =
      $this->getConfigurationManager()->getConfigFromAnySource(
        'unit.csharp.discovery');
    if ($this->discoveryRules === null) {
      throw new Exception(
        pht(
          'You must configure discovery rules to map C# files '.
          'back to test projects (`%s` in %s).',
          'unit.csharp.discovery',
          '.arcconfig'));
    }

    // Determine xUnit test runner path.
    if ($this->xunitHintPath === null) {
      $this->xunitHintPath =
        $this->getConfigurationManager()->getConfigFromAnySource(
          'unit.csharp.xunit.binary');
    }
    $xunit = $this->projectRoot.DIRECTORY_SEPARATOR.$this->xunitHintPath;
    if (file_exists($xunit) && $this->xunitHintPath !== null) {
      $this->testEngine = Filesystem::resolvePath($xunit);
    } else if (Filesystem::binaryExists('xunit.console.clr4.exe')) {
      $this->testEngine = 'xunit.console.clr4.exe';
    } else {
      throw new Exception(
        pht(
          "Unable to locate xUnit console runner. Configure ".
          "it with the `%s' option in %s.",
          'unit.csharp.xunit.binary',
          '.arcconfig'));
    }
  }

  /**
   * Main entry point for the test engine. Determines what assemblies to build
   * and test based on the files that have changed.
   *
   * @return array   Array of test results.
   */
  public function run() {
    $this->loadEnvironment();

    if ($this->getRunAllTests()) {
      $paths = id(new FileFinder($this->projectRoot))->find();
    } else {
      $paths = $this->getPaths();
    }

    return $this->runAllTests($this->mapPathsToResults($paths));
  }

  /**
   * Applies the discovery rules to the set of paths specified.
   *
   * @param  array   Array of paths.
   * @return array   Array of paths to test projects and assemblies.
   */
  public function mapPathsToResults(array $paths) {
    $results = array();
    foreach ($this->discoveryRules as $regex => $targets) {
      $regex = str_replace('/', '\\/', $regex);
      foreach ($paths as $path) {
        if (preg_match('/'.$regex.'/', $path) === 1) {
          foreach ($targets as $target) {
            // Index 0 is the test project (.csproj file)
            // Index 1 is the output assembly (.dll file)
            $project = preg_replace('/'.$regex.'/', $target[0], $path);
            $project = $this->projectRoot.DIRECTORY_SEPARATOR.$project;
            $assembly = preg_replace('/'.$regex.'/', $target[1], $path);
            $assembly = $this->projectRoot.DIRECTORY_SEPARATOR.$assembly;
            if (file_exists($project)) {
              $project = Filesystem::resolvePath($project);
              $assembly = Filesystem::resolvePath($assembly);

              // Check to ensure uniqueness.
              $exists = false;
              foreach ($results as $existing) {
                if ($existing['assembly'] === $assembly) {
                  $exists = true;
                  break;
                }
              }

              if (!$exists) {
                $results[] = array(
                  'project' => $project,
                  'assembly' => $assembly,
                );
              }
            }
          }
        }
      }
    }
    return $results;
  }

  /**
   * Builds and runs the specified test assemblies.
   *
   * @param  array   Array of paths to test project files.
   * @return array   Array of test results.
   */
  public function runAllTests(array $test_projects) {
    if (empty($test_projects)) {
      return array();
    }

    $results = array();
    $results[] = $this->generateProjects();
    if ($this->resultsContainFailures($results)) {
      return array_mergev($results);
    }
    $results[] = $this->buildProjects($test_projects);
    if ($this->resultsContainFailures($results)) {
      return array_mergev($results);
    }
    $results[] = $this->testAssemblies($test_projects);

    return array_mergev($results);
  }

  /**
   * Determine whether or not a current set of results contains any failures.
   * This is needed since we build the assemblies as part of the unit tests, but
   * we can't run any of the unit tests if the build fails.
   *
   * @param  array   Array of results to check.
   * @return bool    If there are any failures in the results.
   */
  private function resultsContainFailures(array $results) {
    $results = array_mergev($results);
    foreach ($results as $result) {
      if ($result->getResult() != ArcanistUnitTestResult::RESULT_PASS) {
        return true;
      }
    }
    return false;
  }

  /**
   * If the `Build` directory exists, we assume that this is a multi-platform
   * project that requires generation of C# project files. Because we want to
   * test that the generation and subsequent build is whole, we need to
   * regenerate any projects in case the developer has added files through an
   * IDE and then forgotten to add them to the respective `.definitions` file.
   * By regenerating the projects we ensure that any missing definition entries
   * will cause the build to fail.
   *
   * @return array   Array of test results.
   */
  private function generateProjects() {
    // No "Build" directory; so skip generation of projects.
    if (!is_dir(Filesystem::resolvePath($this->projectRoot.'/Build'))) {
      return array();
    }

    // No "Protobuild.exe" file; so skip generation of projects.
    if (!is_file(Filesystem::resolvePath(
      $this->projectRoot.'/Protobuild.exe'))) {

      return array();
    }

    // Work out what platform the user is building for already.
    $platform = phutil_is_windows() ? 'Windows' : 'Linux';
    $files = Filesystem::listDirectory($this->projectRoot);
    foreach ($files as $file) {
      if (strtolower(substr($file, -4)) == '.sln') {
        $parts = explode('.', $file);
        $platform = $parts[count($parts) - 2];
        break;
      }
    }

    $regenerate_start = microtime(true);
    $regenerate_future = new ExecFuture(
      '%C Protobuild.exe --resync %s',
      $this->runtimeEngine,
      $platform);
    $regenerate_future->setCWD(Filesystem::resolvePath(
      $this->projectRoot));
    $results = array();
    $result = new ArcanistUnitTestResult();
    $result->setName(pht('(regenerate projects for %s)', $platform));

    try {
      $regenerate_future->resolvex();
      $result->setResult(ArcanistUnitTestResult::RESULT_PASS);
    } catch (CommandException $exc) {
      if ($exc->getError() > 1) {
        throw $exc;
      }
      $result->setResult(ArcanistUnitTestResult::RESULT_FAIL);
      $result->setUserData($exc->getStdout());
    }

    $result->setDuration(microtime(true) - $regenerate_start);
    $results[] = $result;
    return $results;
  }

  /**
   * Build the projects relevant for the specified test assemblies and return
   * the results of the builds as test results. This build also passes the
   * "SkipTestsOnBuild" parameter when building the projects, so that MSBuild
   * conditionals can be used to prevent any tests running as part of the
   * build itself (since the unit tester is about to run each of the tests
   * individually).
   *
   * @param  array   Array of test assemblies.
   * @return array   Array of test results.
   */
  private function buildProjects(array $test_assemblies) {
    $build_futures = array();
    $build_failed = false;
    $build_start = microtime(true);
    $results = array();
    foreach ($test_assemblies as $test_assembly) {
      $build_future = new ExecFuture(
        '%C %s',
        $this->buildEngine,
        '/p:SkipTestsOnBuild=True');
      $build_future->setCWD(Filesystem::resolvePath(
        dirname($test_assembly['project'])));
      $build_futures[$test_assembly['project']] = $build_future;
    }
    $iterator = id(new FutureIterator($build_futures))->limit(1);
    foreach ($iterator as $test_assembly => $future) {
      $result = new ArcanistUnitTestResult();
      $result->setName('(build) '.$test_assembly);

      try {
        $future->resolvex();
        $result->setResult(ArcanistUnitTestResult::RESULT_PASS);
      } catch (CommandException $exc) {
        if ($exc->getError() > 1) {
          throw $exc;
        }
        $result->setResult(ArcanistUnitTestResult::RESULT_FAIL);
        $result->setUserData($exc->getStdout());
        $build_failed = true;
      }

      $result->setDuration(microtime(true) - $build_start);
      $results[] = $result;
    }
    return $results;
  }

  /**
   * Build the future for running a unit test. This can be overridden to enable
   * support for code coverage via another tool.
   *
   * @param  string  Name of the test assembly.
   * @return array   The future, output filename and coverage filename
   *                 stored in an array.
   */
  protected function buildTestFuture($test_assembly) {
      // FIXME: Can't use TempFile here as xUnit doesn't like
      // UNIX-style full paths. It sees the leading / as the
      // start of an option flag, even when quoted.
      $xunit_temp = Filesystem::readRandomCharacters(10).'.results.xml';
      if (file_exists($xunit_temp)) {
        unlink($xunit_temp);
      }
      $future = new ExecFuture(
        '%C %s /xml %s',
        trim($this->runtimeEngine.' '.$this->testEngine),
        $test_assembly,
        $xunit_temp);
      $folder = Filesystem::resolvePath($this->projectRoot);
      $future->setCWD($folder);
      $combined = $folder.'/'.$xunit_temp;
      if (phutil_is_windows()) {
        $combined = $folder.'\\'.$xunit_temp;
      }
      return array($future, $combined, null);
  }

  /**
   * Run the xUnit test runner on each of the assemblies and parse the
   * resulting XML.
   *
   * @param  array   Array of test assemblies.
   * @return array   Array of test results.
   */
  private function testAssemblies(array $test_assemblies) {
    $results = array();

    // Build the futures for running the tests.
    $futures = array();
    $outputs = array();
    $coverages = array();
    foreach ($test_assemblies as $test_assembly) {
      list($future_r, $xunit_temp, $coverage) =
        $this->buildTestFuture($test_assembly['assembly']);
      $futures[$test_assembly['assembly']] = $future_r;
      $outputs[$test_assembly['assembly']] = $xunit_temp;
      $coverages[$test_assembly['assembly']] = $coverage;
    }

    // Run all of the tests.
    $futures = id(new FutureIterator($futures))
      ->limit(8);
    foreach ($futures as $test_assembly => $future) {
      list($err, $stdout, $stderr) = $future->resolve();

      if (file_exists($outputs[$test_assembly])) {
        $result = $this->parseTestResult(
          $outputs[$test_assembly],
          $coverages[$test_assembly]);
        $results[] = $result;
        unlink($outputs[$test_assembly]);
      } else {
        // FIXME: There's a bug in Mono which causes a segmentation fault
        // when xUnit.NET runs; this causes the XML file to not appear
        // (depending on when the segmentation fault occurs). See
        // https://bugzilla.xamarin.com/show_bug.cgi?id=16379
        // for more information.

        // Since it's not possible for the user to correct this error, we
        // ignore the fact the tests didn't run here.
      }
    }

    return array_mergev($results);
  }

  /**
   * Returns null for this implementation as xUnit does not support code
   * coverage directly. Override this method in another class to provide code
   * coverage information (also see @{class:CSharpToolsUnitEngine}).
   *
   * @param  string  The name of the coverage file if one was provided by
   *                 `buildTestFuture`.
   * @return array   Code coverage results, or null.
   */
  protected function parseCoverageResult($coverage) {
    return null;
  }

  /**
   * Parses the test results from xUnit.
   *
   * @param  string  The name of the xUnit results file.
   * @param  string  The name of the coverage file if one was provided by
   *                 `buildTestFuture`. This is passed through to
   *                 `parseCoverageResult`.
   * @return array   Test results.
   */
  private function parseTestResult($xunit_tmp, $coverage) {
    $xunit_dom = new DOMDocument();
    $xunit_dom->loadXML(Filesystem::readFile($xunit_tmp));

    $results = array();
    $tests = $xunit_dom->getElementsByTagName('test');
    foreach ($tests as $test) {
      $name = $test->getAttribute('name');
      $time = $test->getAttribute('time');
      $status = ArcanistUnitTestResult::RESULT_UNSOUND;
      switch ($test->getAttribute('result')) {
        case 'Pass':
          $status = ArcanistUnitTestResult::RESULT_PASS;
          break;
        case 'Fail':
          $status = ArcanistUnitTestResult::RESULT_FAIL;
          break;
        case 'Skip':
          $status = ArcanistUnitTestResult::RESULT_SKIP;
          break;
      }
      $userdata = '';
      $reason = $test->getElementsByTagName('reason');
      $failure = $test->getElementsByTagName('failure');
      if ($reason->length > 0 || $failure->length > 0) {
        $node = ($reason->length > 0) ? $reason : $failure;
        $message = $node->item(0)->getElementsByTagName('message');
        if ($message->length > 0) {
          $userdata = $message->item(0)->nodeValue;
        }
        $stacktrace = $node->item(0)->getElementsByTagName('stack-trace');
        if ($stacktrace->length > 0) {
          $userdata .= "\n".$stacktrace->item(0)->nodeValue;
        }
      }

      $result = new ArcanistUnitTestResult();
      $result->setName($name);
      $result->setResult($status);
      $result->setDuration($time);
      $result->setUserData($userdata);
      if ($coverage != null) {
        $result->setCoverage($this->parseCoverageResult($coverage));
      }
      $results[] = $result;
    }

    return $results;
  }

}
