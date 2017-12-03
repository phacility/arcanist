<?php

/**
 * Basic test engine for running tests using Maven.
 * Forked from: https://secure.phabricator.com/F738966
 *
 * Usage:
 *  1)  Add the following to .arcconfig
 *     "unit.engine": "MavenTestEngine",
 *     "unit.engine.command": "mvn test "
 *  2)  Run `arc unit`
 */
final class MavenTestEngine extends ArcanistUnitTestEngine {

  private $project_root;

  public function run() {

    $working_copy = $this->getWorkingCopy();
    $this->project_root = $working_copy->getProjectRoot();
    $config_manager = $this->getConfigurationManager();
    $command = $config_manager->getConfigFromAnySource('unit.engine.command');
    echo "Running unit tests...\n    `$command`\n\n";
    // We only want to report results for tests that actually ran, so
    // we'll compare the test result files' timestamps to the start time
    // of the test run. This will probably break if multiple test runs
    // are happening in parallel, but if that's happening then we can't
    // count on the results files being intact anyway.
    $start_time = time();

    $maven_top_dirs = $this->findTopLevelMavenDirectories();
    $maven_failed = false;
    $future = new ExecFuture($command);
    $future->setCWD($this->project_root);
    list($status, $stdout, $stderr) = $future->resolve();
    if ($status) {
        // Maven exits with a nonzero status if there were test failures
        // or if there was a compilation error.
        $maven_failed = true;
    }

    /**
     * NOTE: This optimization doesn't work with multi-modules.
     * Removing this for now until we can fix this.
     * TODO(johnie@uber.com)
     *
    // We'll figure out if any of the modified files we're testing are in
    // Maven directories. We won't want to run a bunch of Java tests for
    // changes to CSS files or whatever.
    $modified_paths = $this->getModifiedPaths();

    $maven_failed = false;

    foreach ($maven_top_dirs as $dir) {
      $dir_with_trailing_slash = $dir . '/';
      foreach ($modified_paths as $path) {
        if ($dir_with_trailing_slash ===
            substr($path, 0, strlen($dir_with_trailing_slash))) {
          $future = new ExecFuture($command);
          $future->setCWD($dir);
          list($status, $stdout, $stderr) = $future->resolve();
          if ($status) {
            // Maven exits with a nonzero status if there were test failures
            // or if there was a compilation error.
            $maven_failed = true;
            break 2;
          }
          break;
        }
      }
    }
    **/

    $testResults = $this->parseTestResultsSince($start_time);
    if ($maven_failed) {
      // If there wasn't a test failure, then synthesize one to represent
      // the failure of the test run as a whole, since it probably means the
      // code failed to compile.
      $found_failure = false;
      foreach ($testResults as $testResult) {
        if ($testResult->getResult() === ArcanistUnitTestResult::RESULT_FAIL ||
            $testResult->getResult() === ArcanistUnitTestResult::RESULT_BROKEN) {
          $found_failure = true;
          break;
        }
      }

      if (!$found_failure) {
        $testResult = new ArcanistUnitTestResult();
        $testResult->setResult(ArcanistUnitTestResult::RESULT_BROKEN);
        $testResult->setName('mvn test');
        $testResults[] = $testResult;
      }
    }

    return $testResults;
  }

  /**
   * Returns an array of the full canonical paths to all the Maven directories
   * (directories containing pom.xml files) in the project.
   */
  private function findMavenDirectories() {
    if (file_exists($this->project_root . "/.git")) {
      // The fastest way to find all the pom.xml files is to let git scan
      // its index.
      $future = new ExecFuture('git ls-files \*/pom.xml');
    } else {
      // Not a git repo. Do it the old-fashioned way.
      $future = new ExecFuture('find . -name pom.xml -print');
    }

    // TODO: This will find *all* the pom.xml files in the working copy.
    // Need to obey the optional paths argument to "arc unit" to let users
    // run just a subset of tests.
    $future->setCWD($this->project_root);
    list($stdout) = $future->resolvex();

    $poms = explode("\n", trim($stdout));
    if (!$poms) {
      throw new Exception("No pom.xml files found");
    }

    $maven_dirs = array_map(function($pom) {
      $maven_dir = dirname($pom);
      return realpath($this->project_root . '/' . $maven_dir);
    }, $poms);

    return $maven_dirs;
  }

  /**
   * Returns an array of the full canonical paths to all the top-level Maven
   * directories in the project. A directory is not considered top-level if
   * one of its parent directories has a pom.xml.
   */
  private function findTopLevelMavenDirectories() {
    $maven_dirs = $this->findMavenDirectories();
    sort($maven_dirs);

    $previous_top_dir = '-';
    $top_dirs = array();
    foreach ($maven_dirs as $maven_dir) {
      if ($previous_top_dir !==
          substr($maven_dir . '/', 0, strlen($previous_top_dir))) {
        $previous_top_dir = $maven_dir . '/';
        $top_dirs[] = $maven_dir;
      }
    }

    return $top_dirs;
  }

  /**
   * Returns an array of paths to the JUnit test result XML files in the
   * project.
   */
  private function findTestResultFiles() {
    $maven_dirs = $this->findMavenDirectories();
    $result_dirs = array();
    foreach ($maven_dirs as $maven_dir) {
      $fullpath = $maven_dir .  '/target/surefire-reports';
      if (file_exists($fullpath)) {
        $result_dirs[] = $fullpath;
      }
    }

    $result_files = array();
    foreach ($result_dirs as $result_dir) {
      $xmlfiles = glob($result_dir . "/*.xml");
      $result_files = array_merge($result_files, $xmlfiles);
    }

    return $result_files;
  }

  /**
   * Returns the full paths to all the files modified in the workspace.
   */
  private function getModifiedPaths() {
    $paths = $this->getPaths();
    return array_map(function($path) {
      return realpath($this->project_root . '/' . $path);
    }, $paths);
  }

  /**
   * Parses all the test results that have been written since a particular
   * starting time.
   */
  private function parseTestResultsSince($start_time) {
    $parser = new ArcanistXUnitTestResultParser();
    $results = array();

    $result_files = $this->findTestResultFiles();

    foreach ($result_files as $file) {
      $stat = stat($file);
      if ($stat && $stat['mtime'] >= $start_time) {
        $new_results = $parser->parseTestResults(Filesystem::readFile($file));
        if ($new_results) {
          $results = array_merge($results, $new_results);
        }
      }
    }

    return $results;
  }
}
