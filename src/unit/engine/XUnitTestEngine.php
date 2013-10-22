<?php

/**
 * Uses xUnit (http://xunit.codeplex.com/) to test C# code.
 *
 * Assumes that when modifying a file with a path like `SomeAssembly/MyFile.cs`,
 * that the test assembly that verifies the functionality of `SomeAssembly` is
 * located at `SomeAssembly.Tests`.
 *
 * @group unitrun
 * @concrete-extensible
 */
class XUnitTestEngine extends ArcanistBaseUnitTestEngine {

  protected $runtimeEngine;
  protected $buildEngine;
  protected $testEngine;
  protected $projectRoot;
  protected $xunitHintPath;

  /**
   * This test engine supports running all tests.
   */
  protected function supportsRunAllTests() {
    return true;
  }

  /**
   * Determines what executables and test paths to use.  Between platforms
   * this also changes whether the test engine is run under .NET or Mono.  It
   * also ensures that all of the required binaries are available for the tests
   * to run successfully.
   *
   * @return void
   */
  protected function loadEnvironment($config_item = 'unit.xunit.binary') {
    $this->projectRoot = $this->getWorkingCopy()->getProjectRoot();

    // Determine build engine.
    if (Filesystem::binaryExists("msbuild")) {
      $this->buildEngine = "msbuild";
    } else if (Filesystem::binaryExists("xbuild")) {
      $this->buildEngine = "xbuild";
    } else {
      throw new Exception("Unable to find msbuild or xbuild in PATH!");
    }

    // Determine runtime engine (.NET or Mono).
    if (phutil_is_windows()) {
      $this->runtimeEngine = "";
    } else if (Filesystem::binaryExists("mono")) {
      $this->runtimeEngine = Filesystem::resolveBinary("mono");
    } else {
      throw new Exception("Unable to find Mono and you are not on Windows!");
    }

    // Determine xUnit test runner path.
    if ($this->xunitHintPath === null) {
      $this->xunitHintPath =
        $this->getConfigurationManager()->getConfigFromAnySource(
          'unit.xunit.binary');
    }
    if ($this->xunitHintPath === null) {
    }
    $xunit = $this->projectRoot."/".$this->xunitHintPath;
    if (file_exists($xunit)) {
      $this->testEngine = Filesystem::resolvePath($xunit);
    } else if (Filesystem::binaryExists("xunit.console.clr4.exe")) {
      $this->testEngine = "xunit.console.clr4.exe";
    } else {
      throw new Exception(
        "Unable to locate xUnit console runner.  Configure ".
        "it with the `$config_item' option in .arcconfig");
    }
  }

  /**
   * Returns all available tests and related projects.  Recurses into
   * Protobuild submodules if they are present.
   *
   * @return array   Mappings of test project to project being tested.
   */
  public function getAllAvailableTestsAndRelatedProjects($path = null) {
    if ($path == null) {
      $path = $this->projectRoot;
    }
    $entries = Filesystem::listDirectory($path);
    $mappings = array();
    foreach ($entries as $entry) {
      if (substr($entry, -6) === ".Tests") {
        if (is_dir($path."/".$entry)) {
          $mappings[$path."/".$entry] = $path."/".
            substr($entry, 0, strlen($entry) - 6);
        }
      } elseif (is_dir($path."/".$entry."/Build")) {
        if (file_exists($path."/".$entry."/Build/Module.xml")) {
          // The entry is a Protobuild submodule, which we should
          // also recurse into.
          $submappings =
            $this->getAllAvailableTestsAndRelatedProjects($path."/".$entry);
          foreach ($submappings as $key => $value) {
            $mappings[$key] = $value;
          }
        }
      }
    }
    return $mappings;
  }

  /**
   * Main entry point for the test engine.  Determines what assemblies to
   * build and test based on the files that have changed.
   *
   * @return array   Array of test results.
   */
  public function run() {

    $this->loadEnvironment();

    $affected_tests = array();
    if ($this->getRunAllTests()) {
      echo "Loading tests..."."\n";
      $entries = $this->getAllAvailableTestsAndRelatedProjects();
      foreach ($entries as $key => $value) {
        echo "Test: ".substr($key, strlen($this->projectRoot) + 1)."\n";
        $affected_tests[] = substr($key, strlen($this->projectRoot) + 1);
      }
    } else {
      $paths = $this->getPaths();

      foreach ($paths as $path) {
        if (substr($path, -4) == ".dll" ||
            substr($path, -4) == ".mdb") {
          continue;
        }
        if (substr_count($path, "/") > 0) {
          $components = explode("/", $path);
          $affected_assembly = $components[0];

          // If the change is made inside an assembly that has a `.Tests`
          // extension, then the developer has changed the actual tests.
          if (substr($affected_assembly, -6) === ".Tests") {
            $affected_assembly_path = Filesystem::resolvePath(
              $affected_assembly);
            $test_assembly = $affected_assembly;
          } else {
            $affected_assembly_path = Filesystem::resolvePath(
              $affected_assembly.".Tests");
            $test_assembly = $affected_assembly.".Tests";
          }
          if (is_dir($affected_assembly_path) &&
              !in_array($test_assembly, $affected_tests)) {
            $affected_tests[] = $test_assembly;
          }
        }
      }
    }

    return $this->runAllTests($affected_tests);
  }

  /**
   * Builds and runs the specified test assemblies.
   *
   * @param  array   Array of test assemblies.
   * @return array   Array of test results.
   */
  public function runAllTests(array $test_assemblies) {
    if (empty($test_assemblies)) {
      return array();
    }

    $results = array();
    $results[] = $this->generateProjects();
    if ($this->resultsContainFailures($results)) {
      return array_mergev($results);
    }
    $results[] = $this->buildProjects($test_assemblies);
    if ($this->resultsContainFailures($results)) {
      return array_mergev($results);
    }
    $results[] = $this->testAssemblies($test_assemblies);

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
   * project that requires generation of C# project files.  Because we want to
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
    if (!is_dir(Filesystem::resolvePath($this->projectRoot."/Build"))) {
      return array();
    }

    // Work out what platform the user is building for already.
    $platform = phutil_is_windows() ? "Windows" : "Linux";
    $files = Filesystem::listDirectory($this->projectRoot);
    foreach ($files as $file) {
      if (strtolower(substr($file, -4)) == ".sln") {
        $parts = explode(".", $file);
        $platform = $parts[count($parts) - 2];
        break;
      }
    }

    $regenerate_start = microtime(true);
    $regenerate_future = new ExecFuture(
      "%C Protobuild.exe --resync %s",
      $this->runtimeEngine,
      $platform);
    $regenerate_future->setCWD(Filesystem::resolvePath(
      $this->projectRoot));
    $results = array();
    $result = new ArcanistUnitTestResult();
    $result->setName("(regenerate projects for $platform)");

    try {
      $regenerate_future->resolvex();
      $result->setResult(ArcanistUnitTestResult::RESULT_PASS);
    } catch(CommandException $exc) {
      if ($exc->getError() > 1) {
        throw $exc;
      }
      $result->setResult(ArcanistUnitTestResult::RESULT_FAIL);
      $result->setUserdata($exc->getStdout());
    }

    $result->setDuration(microtime(true) - $regenerate_start);
    $results[] = $result;
    return $results;
  }

  /**
   * Build the projects relevant for the specified test assemblies and return
   * the results of the builds as test results.  This build also passes the
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
        "%C %s",
        $this->buildEngine,
        "/p:SkipTestsOnBuild=True");
      $build_future->setCWD(Filesystem::resolvePath(
        $this->projectRoot."/".$test_assembly));
      $build_futures[$test_assembly] = $build_future;
    }
    $iterator = Futures($build_futures)->limit(1);
    foreach ($iterator as $test_assembly => $future) {
      $result = new ArcanistUnitTestResult();
      $result->setName("(build) ".$test_assembly);

      try {
        $future->resolvex();
        $result->setResult(ArcanistUnitTestResult::RESULT_PASS);
      } catch(CommandException $exc) {
        if ($exc->getError() > 1) {
          throw $exc;
        }
        $result->setResult(ArcanistUnitTestResult::RESULT_FAIL);
        $result->setUserdata($exc->getStdout());
        $build_failed = true;
      }

      $result->setDuration(microtime(true) - $build_start);
      $results[] = $result;
    }
    return $results;
  }

  /**
   * Build the future for running a unit test.  This can be
   * overridden to enable support for code coverage via
   * another tool
   *
   * @param  string  Name of the test assembly.
   * @return array   The future, output filename and coverage filename
   *                 stored in an array.
   */
  protected function buildTestFuture($test_assembly) {
      // FIXME: Can't use TempFile here as xUnit doesn't like
      // UNIX-style full paths.  It sees the leading / as the
      // start of an option flag, even when quoted.
      $xunit_temp = $test_assembly.".results.xml";
      if (file_exists($xunit_temp)) {
        unlink($xunit_temp);
      }
      $future = new ExecFuture(
        "%C %s /xml %s /silent",
        trim($this->runtimeEngine." ".$this->testEngine),
        $test_assembly."/bin/Debug/".$test_assembly.".dll",
        $xunit_temp);
      $future->setCWD(Filesystem::resolvePath($this->projectRoot));
      return array($future, $xunit_temp, null);
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
      list($future, $xunit_temp, $coverage) =
        $this->buildTestFuture($test_assembly);
      $futures[$test_assembly] = $future;
      $outputs[$test_assembly] = $xunit_temp;
      $coverages[$test_assembly] = $coverage;
    }

    // Run all of the tests.
    foreach (Futures($futures) as $test_assembly => $future) {
      $future->resolve();

      if (file_exists($outputs[$test_assembly])) {
        $result = $this->parseTestResult(
          $outputs[$test_assembly],
          $coverages[$test_assembly]);
        $results[] = $result;
        unlink($outputs[$test_assembly]);
      } else {
        $result = new ArcanistUnitTestResult();
        $result->setName("(execute) ".$test_assembly);
        $result->setResult(ArcanistUnitTestResult::RESULT_BROKEN);
        $result->setUserData($outputs[$test_assembly]." not found on disk.");
        $results[] = array($result);
      }
    }

    return array_mergev($results);
  }

  /**
   * Returns null for this implementation as xUnit does not support code
   * coverage directly.  Override this method in another class to provide
   * code coverage information (also see `CSharpToolsUnitEngine`).
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
   *                 `buildTestFuture`.  This is passed through to
   *                 `parseCoverageResult`.
   * @return array   Test results.
   */
  private function parseTestResult($xunit_tmp, $coverage) {
    $xunit_dom = new DOMDocument();
    $xunit_dom->loadXML(Filesystem::readFile($xunit_tmp));

    $results = array();
    $tests = $xunit_dom->getElementsByTagName("test");
    foreach ($tests as $test) {
      $name = $test->getAttribute("name");
      $time = $test->getAttribute("time");
      $status = ArcanistUnitTestResult::RESULT_UNSOUND;
      switch ($test->getAttribute("result")) {
        case "Pass":
          $status = ArcanistUnitTestResult::RESULT_PASS;
          break;
        case "Fail":
          $status = ArcanistUnitTestResult::RESULT_FAIL;
          break;
        case "Skip":
          $status = ArcanistUnitTestResult::RESULT_SKIP;
          break;
      }
      $userdata = "";
      $reason = $test->getElementsByTagName("reason");
      $failure = $test->getElementsByTagName("failure");
      if ($reason->length > 0 || $failure->length > 0) {
        $node = ($reason->length > 0) ? $reason : $failure;
        $message = $node->item(0)->getElementsByTagName("message");
        if ($message->length > 0) {
          $userdata = $message->item(0)->nodeValue;
        }
        $stacktrace = $node->item(0)->getElementsByTagName("stack-trace");
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
