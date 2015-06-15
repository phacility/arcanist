<?php

/**
 * Abstract base class for test result parsers.
 */
abstract class ArcanistTestResultParser extends Phobject {

  protected $enableCoverage;
  protected $projectRoot;
  protected $coverageFile;
  protected $stderr;
  protected $affectedTests;

  public function setEnableCoverage($enable_coverage) {
    $this->enableCoverage = $enable_coverage;
    return $this;
  }

  public function setProjectRoot($project_root) {
    $this->projectRoot = $project_root;
    return $this;
  }

  public function setCoverageFile($coverage_file) {
    $this->coverageFile = $coverage_file;
    return $this;
  }

  public function setAffectedTests($affected_tests) {
    $this->affectedTests = $affected_tests;
    return $this;
  }

  public function setStderr($stderr) {
    $this->stderr = $stderr;
    return $this;
  }

  /**
   * Parse test results from provided input and return an array of
   * @{class:ArcanistUnitTestResult}.
   *
   * @param string Path to test.
   * @param string String containing test results.
   * @return array
   */
  abstract public function parseTestResults($path, $test_results);

}
