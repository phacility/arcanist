<?php

/**
 * Manages unit test execution.
 */
abstract class ArcanistUnitTestEngine extends Phobject {

  private $workingCopy;
  private $paths = array();
  private $enableCoverage;
  private $runAllTests;
  private $configurationManager;
  protected $renderer;

  final public function __construct() {}

  public function getEngineConfigurationName() {
    return null;
  }

  final public function setRunAllTests($run_all_tests) {
    if (!$this->supportsRunAllTests() && $run_all_tests) {
      throw new Exception(
        pht(
          "Engine '%s' does not support %s.",
          get_class($this),
          '--everything'));
    }

    $this->runAllTests = $run_all_tests;
    return $this;
  }

  final public function getRunAllTests() {
    return $this->runAllTests;
  }

  protected function supportsRunAllTests() {
    return false;
  }

  final public function setConfigurationManager(
    ArcanistConfigurationManager $configuration_manager) {
    $this->configurationManager = $configuration_manager;
    return $this;
  }

  final public function getConfigurationManager() {
    return $this->configurationManager;
  }

  final public function setWorkingCopy(
    ArcanistWorkingCopyIdentity $working_copy) {

    $this->workingCopy = $working_copy;
    return $this;
  }

  final public function getWorkingCopy() {
    return $this->workingCopy;
  }

  final public function setPaths(array $paths) {
    $this->paths = $paths;
    return $this;
  }

  final public function getPaths() {
    return $this->paths;
  }

  final public function setEnableCoverage($enable_coverage) {
    $this->enableCoverage = $enable_coverage;
    return $this;
  }

  final public function getEnableCoverage() {
    return $this->enableCoverage;
  }

  final public function setRenderer(ArcanistUnitRenderer $renderer = null) {
    $this->renderer = $renderer;
    return $this;
  }

  abstract public function run();

  /**
   * Modify the return value of this function in the child class, if you do
   * not need to echo the test results after all the tests have been run. This
   * is the case for example when the child class prints the tests results
   * while the tests are running.
   */
  public function shouldEchoTestResults() {
    return true;
  }

}
