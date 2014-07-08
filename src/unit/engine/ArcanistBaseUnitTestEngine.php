<?php

/**
 * Manages unit test execution.
 */
abstract class ArcanistBaseUnitTestEngine {

  private $workingCopy;
  private $paths;
  private $arguments = array();
  protected $diffID;
  private $enableAsyncTests;
  private $enableCoverage;
  private $runAllTests;
  protected $renderer;


  public function setRunAllTests($run_all_tests) {
    if (!$this->supportsRunAllTests() && $run_all_tests) {
      $class = get_class($this);
      throw new Exception(
        "Engine '{$class}' does not support --everything.");
    }

    $this->runAllTests = $run_all_tests;
    return $this;
  }

  public function getRunAllTests() {
    return $this->runAllTests;
  }

  protected function supportsRunAllTests() {
    return false;
  }

  final public function __construct() {

  }

  public function setConfigurationManager(
    ArcanistConfigurationManager $configuration_manager) {
    $this->configurationManager = $configuration_manager;
    return $this;
  }

  public function getConfigurationManager() {
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

  final public function setArguments(array $arguments) {
    $this->arguments = $arguments;
    return $this;
  }

  final public function getArgument($key, $default = null) {
    return idx($this->arguments, $key, $default);
  }

  final public function setEnableAsyncTests($enable_async_tests) {
    $this->enableAsyncTests = $enable_async_tests;
    return $this;
  }

  final public function getEnableAsyncTests() {
    return $this->enableAsyncTests;
  }

  final public function setEnableCoverage($enable_coverage) {
    $this->enableCoverage = $enable_coverage;
    return $this;
  }

  final public function getEnableCoverage() {
    return $this->enableCoverage;
  }

  public function setRenderer(ArcanistUnitRenderer $renderer) {
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
