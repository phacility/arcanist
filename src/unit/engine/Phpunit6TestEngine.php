<?php

/**
 * PHPUnit 6 wrapper.
 *
 * This wrapper was created because original PhpunitTestEngine does not work
 * with PHPUnit 6 version. Wrapper is not officially supported by Phacility.
 */
final class Phpunit6TestEngine extends ArcanistUnitTestEngine {

  private $configFile;
  private $phpunitBinary = 'phpunit';
  private $affectedTests;
  private $projectRoot;

  public function run() {
    $this->projectRoot = $this->getWorkingCopy()->getProjectRoot();
    $this->affectedTests = array();
    foreach ($this->getPaths() as $path) {

      $path = Filesystem::resolvePath($path, $this->projectRoot);

      // TODO: add support for directories
      // Users can call phpunit on the directory themselves
      if (is_dir($path)) {
        continue;
      }

      // Not sure if it would make sense to go further if
      // it is not a .php file
      if (substr($path, -4) != '.php') {
        continue;
      }

      if (substr($path, -8) == 'Test.php') {
        // Looks like a valid test file name.
        $this->affectedTests[$path] = $path;
        continue;
      }

      if ($test = $this->findTestFile($path)) {
        $this->affectedTests[$path] = $test;
      }

    }

    if (empty($this->affectedTests)) {
      throw new ArcanistNoEffectException(pht('No tests to run.'));
    }

    $this->prepareConfigFile();
    $futures = array();
    $tmpfiles = array();
    foreach ($this->affectedTests as $class_path => $test_path) {
      if (!Filesystem::pathExists($test_path)) {
        continue;
      }
      $xml_tmp = new TempFile();
      $clover_tmp = null;
      $clover = null;
      if ($this->getEnableCoverage() !== false) {
        $clover_tmp = new TempFile();
        $clover = csprintf('--coverage-clover %s', $clover_tmp);
      }

      $config = $this->configFile ? csprintf('-c %s', $this->configFile) : null;

      $stderr = '-d display_errors=stderr';

      $futures[$test_path] = new ExecFuture('%C %C %C --log-junit %s %C %s',
        $this->phpunitBinary, $config, $stderr, $xml_tmp, $clover, $test_path);
      $tmpfiles[$test_path] = array(
        'xml' => $xml_tmp,
        'clover' => $clover_tmp,
      );
    }

    $results = array();
    $futures = id(new FutureIterator($futures))
      ->limit(4);
    foreach ($futures as $test => $future) {

      list($err, $stdout, $stderr) = $future->resolve();

      $results[] = $this->parseTestResults(
        $test,
        $tmpfiles[$test]['xml'],
        $tmpfiles[$test]['clover'],
        $stderr);
    }

    return array_mergev($results);
  }

  /**
   * Parse test results from phpunit XML report.
   *
   * @param string $path Path to test
   * @param string $xml_tmp Path to phpunit XML report
   * @param string $clover_tmp Path to phpunit clover report
   * @param string $stderr Data written to stderr
   *
   * @return array
   */
  private function parseTestResults($path, $xml_tmp, $clover_tmp, $stderr) {
    $test_results = Filesystem::readFile($xml_tmp);
    return id(new ArcanistPhpunit6TestResultParser())
      ->setEnableCoverage($this->getEnableCoverage())
      ->setProjectRoot($this->projectRoot)
      ->setCoverageFile($clover_tmp)
      ->setAffectedTests($this->affectedTests)
      ->setStderr($stderr)
      ->parseTestResults($path, $test_results);
  }


  /**
   * Search for test cases for a given file in a large number of "reasonable"
   * locations. See @{method:getSearchLocationsForTests} for specifics.
   *
   * TODO: Add support for finding tests in testsuite folders from
   * phpunit.xml configuration.
   *
   * @param   string      PHP file to locate test cases for.
   * @return  string|null Path to test cases, or null.
   */
  private function findTestFile($path) {
    $root = $this->projectRoot;
    $path = Filesystem::resolvePath($path, $root);

    $file = basename($path);
    $possible_files = array(
      $file,
      substr($file, 0, -4).'Test.php',
    );

    $search = self::getSearchLocationsForTests($path);

    foreach ($search as $search_path) {
      foreach ($possible_files as $possible_file) {
        $full_path = $search_path.$possible_file;
        if (!Filesystem::pathExists($full_path)) {
          // If the file doesn't exist, it's clearly a miss.
          continue;
        }
        if (!Filesystem::isDescendant($full_path, $root)) {
          // Don't look above the project root.
          continue;
        }
        if (0 == strcasecmp(Filesystem::resolvePath($full_path), $path)) {
          // Don't return the original file.
          continue;
        }
        return $full_path;
      }
    }

    return null;
  }


  /**
   * Get places to look for PHP Unit tests that cover a given file. For some
   * file "/a/b/c/X.php", we look in the same directory:
   *
   *  /a/b/c/
   *
   * We then look in all parent directories for a directory named "tests/"
   * (or "Tests/"):
   *
   *  /a/b/c/tests/
   *  /a/b/tests/
   *  /a/tests/
   *  /tests/
   *
   * We also try to replace each directory component with "tests/":
   *
   *  /a/b/tests/
   *  /a/tests/c/
   *  /tests/b/c/
   *
   * We also try to add "tests/" at each directory level:
   *
   *  /a/b/c/tests/
   *  /a/b/tests/c/
   *  /a/tests/b/c/
   *  /tests/a/b/c/
   *
   * This finds tests with a layout like:
   *
   *  docs/
   *  src/
   *  tests/
   *
   * ...or similar. This list will be further pruned by the caller; it is
   * intentionally filesystem-agnostic to be unit testable.
   *
   * @param   string        PHP file to locate test cases for.
   * @return  list<string>  List of directories to search for tests in.
   */
  public static function getSearchLocationsForTests($path) {
    $file = basename($path);
    $dir  = dirname($path);

    $test_dir_names = array('tests', 'Tests');

    $try_directories = array();

    // Try in the current directory.
    $try_directories[] = array($dir);

    // Try in a tests/ directory anywhere in the ancestry.
    foreach (Filesystem::walkToRoot($dir) as $parent_dir) {
      if ($parent_dir == '/') {
        // We'll restore this later.
        $parent_dir = '';
      }
      foreach ($test_dir_names as $test_dir_name) {
        $try_directories[] = array($parent_dir, $test_dir_name);
      }
    }

    // Try replacing each directory component with 'tests/'.
    $parts = trim($dir, DIRECTORY_SEPARATOR);
    $parts = explode(DIRECTORY_SEPARATOR, $parts);
    foreach (array_reverse(array_keys($parts)) as $key) {
      foreach ($test_dir_names as $test_dir_name) {
        $try = $parts;
        $try[$key] = $test_dir_name;
        array_unshift($try, '');
        $try_directories[] = $try;
      }
    }

    // Try adding 'tests/' at each level.
    foreach (array_reverse(array_keys($parts)) as $key) {
      foreach ($test_dir_names as $test_dir_name) {
        $try = $parts;
        $try[$key] = $test_dir_name.DIRECTORY_SEPARATOR.$try[$key];
        array_unshift($try, '');
        $try_directories[] = $try;
      }
    }

    $results = array();
    foreach ($try_directories as $parts) {
      $results[implode(DIRECTORY_SEPARATOR, $parts).DIRECTORY_SEPARATOR] = true;
    }

    return array_keys($results);
  }

  /**
   * Tries to find and update phpunit configuration file based on
   * `phpunit_config` option in `.arcconfig`.
   */
  private function prepareConfigFile() {
    $project_root = $this->projectRoot.DIRECTORY_SEPARATOR;
    $config = $this->getConfigurationManager()->getConfigFromAnySource(
      'phpunit_config');

    if ($config) {
      if (Filesystem::pathExists($project_root.$config)) {
        $this->configFile = $project_root.$config;
      } else {
        throw new Exception(
          pht(
            'PHPUnit configuration file was not found in %s',
            $project_root.$config));
      }
    }
    $bin = $this->getConfigurationManager()->getConfigFromAnySource(
      'unit.phpunit.binary');
    if ($bin) {
      if (Filesystem::binaryExists($bin)) {
        $this->phpunitBinary = $bin;
      } else {
        $this->phpunitBinary = Filesystem::resolvePath($bin, $project_root);
      }
    }
  }

}
