<?php

/**
 * Uses cscover (http://github.com/hach-que/cstools) to report code coverage.
 *
 * This engine inherits from `XUnitTestEngine`, where xUnit is used to actually
 * run the unit tests and this class provides a thin layer on top to collect
 * code coverage data with a third-party tool.
 */
final class CSharpToolsTestEngine extends XUnitTestEngine {

  private $cscoverHintPath;
  private $coverEngine;
  private $cachedResults;
  private $matchRegex;
  private $excludedFiles;

  /**
   * Overridden version of `loadEnvironment` to support a different set of
   * configuration values and to pull in the cstools config for code coverage.
   */
  protected function loadEnvironment() {
    $config = $this->getConfigurationManager();
    $this->cscoverHintPath = $config->getConfigFromAnySource(
      'unit.csharp.cscover.binary');
    $this->matchRegex = $config->getConfigFromAnySource(
      'unit.csharp.coverage.match');
    $this->excludedFiles = $config->getConfigFromAnySource(
      'unit.csharp.coverage.excluded');

    parent::loadEnvironment();

    if ($this->getEnableCoverage() === false) {
      return;
    }

    // Determine coverage path.
    if ($this->cscoverHintPath === null) {
      throw new Exception(
        pht(
          "Unable to locate %s. Configure it with the '%s' option in %s.",
          'cscover',
          'unit.csharp.coverage.binary',
          '.arcconfig'));
    }
    $cscover = $this->projectRoot.DIRECTORY_SEPARATOR.$this->cscoverHintPath;
    if (file_exists($cscover)) {
      $this->coverEngine = Filesystem::resolvePath($cscover);
    } else {
      throw new Exception(
        pht(
          'Unable to locate %s coverage runner (have you built yet?)',
          'cscover'));
    }
  }

  /**
   * Returns whether the specified assembly should be instrumented for
   * code coverage reporting. Checks the excluded file list and the
   * matching regex if they are configured.
   *
   * @return boolean Whether the assembly should be instrumented.
   */
  private function assemblyShouldBeInstrumented($file) {
    if ($this->excludedFiles !== null) {
      if (array_key_exists((string)$file, $this->excludedFiles)) {
        return false;
      }
    }
    if ($this->matchRegex !== null) {
      if (preg_match($this->matchRegex, $file) === 1) {
        return true;
      } else {
        return false;
      }
    }
    return true;
  }

  /**
   * Overridden version of `buildTestFuture` so that the unit test can be run
   * via `cscover`, which instruments assemblies and reports on code coverage.
   *
   * @param  string  Name of the test assembly.
   * @return array   The future, output filename and coverage filename
   *                 stored in an array.
   */
  protected function buildTestFuture($test_assembly) {
    if ($this->getEnableCoverage() === false) {
      return parent::buildTestFuture($test_assembly);
    }

    // FIXME: Can't use TempFile here as xUnit doesn't like
    // UNIX-style full paths. It sees the leading / as the
    // start of an option flag, even when quoted.
    $xunit_temp = Filesystem::readRandomCharacters(10).'.results.xml';
    if (file_exists($xunit_temp)) {
      unlink($xunit_temp);
    }
    $cover_temp = new TempFile();
    $cover_temp->setPreserveFile(true);
    $xunit_cmd = $this->runtimeEngine;
    $xunit_args = null;
    if ($xunit_cmd === '') {
      $xunit_cmd = $this->testEngine;
      $xunit_args = csprintf(
        '%s /xml %s',
        $test_assembly,
        $xunit_temp);
    } else {
      $xunit_args = csprintf(
        '%s %s /xml %s',
        $this->testEngine,
        $test_assembly,
        $xunit_temp);
    }
    $assembly_dir = dirname($test_assembly);
    $assemblies_to_instrument = array();
    foreach (Filesystem::listDirectory($assembly_dir) as $file) {
      if (substr($file, -4) == '.dll' || substr($file, -4) == '.exe') {
        if ($this->assemblyShouldBeInstrumented($file)) {
          $assemblies_to_instrument[] = $assembly_dir.DIRECTORY_SEPARATOR.$file;
        }
      }
    }
    if (count($assemblies_to_instrument) === 0) {
      return parent::buildTestFuture($test_assembly);
    }
    $future = new ExecFuture(
      '%C -o %s -c %s -a %s -w %s %Ls',
      trim($this->runtimeEngine.' '.$this->coverEngine),
      $cover_temp,
      $xunit_cmd,
      $xunit_args,
      $assembly_dir,
      $assemblies_to_instrument);
    $future->setCWD(Filesystem::resolvePath($this->projectRoot));
    return array(
      $future,
      $assembly_dir.DIRECTORY_SEPARATOR.$xunit_temp,
      $cover_temp,
    );
  }

  /**
   * Returns coverage results for the unit tests.
   *
   * @param  string  The name of the coverage file if one was provided by
   *                 `buildTestFuture`.
   * @return array   Code coverage results, or null.
   */
  protected function parseCoverageResult($cover_file) {
    if ($this->getEnableCoverage() === false) {
      return parent::parseCoverageResult($cover_file);
    }
    return $this->readCoverage($cover_file);
  }

  /**
   * Retrieves the cached results for a coverage result file. The coverage
   * result file is XML and can be large depending on what has been instrumented
   * so we cache it in case it's requested again.
   *
   * @param  string  The name of the coverage file.
   * @return array   Code coverage results, or null if not cached.
   */
  private function getCachedResultsIfPossible($cover_file) {
    if ($this->cachedResults == null) {
      $this->cachedResults = array();
    }
    if (array_key_exists((string)$cover_file, $this->cachedResults)) {
      return $this->cachedResults[(string)$cover_file];
    }
    return null;
  }

  /**
   * Stores the code coverage results in the cache.
   *
   * @param  string  The name of the coverage file.
   * @param  array   The results to cache.
   */
  private function addCachedResults($cover_file, array $results) {
    if ($this->cachedResults == null) {
      $this->cachedResults = array();
    }
    $this->cachedResults[(string)$cover_file] = $results;
  }

  /**
   * Processes a set of XML tags as code coverage results. We parse
   * the `instrumented` and `executed` tags with this method so that
   * we can access the data multiple times without a performance hit.
   *
   * @param  array  The array of XML tags to parse.
   * @return array  A PHP array containing the data.
   */
  private function processTags($tags) {
    $results = array();
    foreach ($tags as $tag) {
      $results[] = array(
        'file' => $tag->getAttribute('file'),
        'start' => $tag->getAttribute('start'),
        'end' => $tag->getAttribute('end'),
      );
    }
    return $results;
  }

  /**
   * Reads the code coverage results from the cscover results file.
   *
   * @param  string  The path to the code coverage file.
   * @return array   The code coverage results.
   */
  public function readCoverage($cover_file) {
    $cached = $this->getCachedResultsIfPossible($cover_file);
    if ($cached !== null) {
      return $cached;
    }

    $coverage_dom = new DOMDocument();
    $coverage_dom->loadXML(Filesystem::readFile($cover_file));

    $modified = $this->getPaths();
    $files = array();
    $reports = array();
    $instrumented = array();
    $executed = array();

    $instrumented = $this->processTags(
      $coverage_dom->getElementsByTagName('instrumented'));
    $executed = $this->processTags(
      $coverage_dom->getElementsByTagName('executed'));

    foreach ($instrumented as $instrument) {
      $absolute_file = $instrument['file'];
      $relative_file = substr($absolute_file, strlen($this->projectRoot) + 1);
      if (!in_array($relative_file, $files)) {
        $files[] = $relative_file;
      }
    }

    foreach ($files as $file) {
      $absolute_file = Filesystem::resolvePath(
        $this->projectRoot.DIRECTORY_SEPARATOR.$file);

      // get total line count in file
      $line_count = count(file($absolute_file));

      $coverage = array();
      for ($i = 0; $i < $line_count; $i++) {
        $coverage[$i] = 'N';
      }

      foreach ($instrumented as $instrument) {
        if ($instrument['file'] !== $absolute_file) {
          continue;
        }
        for (
          $i = $instrument['start'];
          $i <= $instrument['end'];
          $i++) {
          $coverage[$i - 1] = 'U';
        }
      }

      foreach ($executed as $execute) {
        if ($execute['file'] !== $absolute_file) {
          continue;
        }
        for (
          $i = $execute['start'];
          $i <= $execute['end'];
          $i++) {
          $coverage[$i - 1] = 'C';
        }
      }

      $reports[$file] = implode($coverage);
    }

    $this->addCachedResults($cover_file, $reports);
    return $reports;
  }

}
