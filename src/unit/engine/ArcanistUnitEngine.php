<?php

abstract class ArcanistUnitEngine
  extends Phobject {

  private $overseer;
  private $includePaths = array();
  private $excludePaths = array();

  final public function setIncludePaths(array $include_paths) {
    $this->includePaths = $include_paths;
    return $this;
  }

  final public function getIncludePaths() {
    return $this->includePaths;
  }

  final public function setExcludePaths(array $exclude_paths) {
    $this->excludePaths = $exclude_paths;
    return $this;
  }

  final public function getExcludePaths() {
    return $this->excludePaths;
  }

  final public function getUnitEngineType() {
    return $this->getPhobjectClassConstant('ENGINETYPE');
  }

  final public function getPath($to_file = null) {
    return Filesystem::concatenatePaths(
      array(
        $this->getOverseer()->getDirectory(),
        $to_file,
      ));
  }

  final public function setOverseer(ArcanistUnitOverseer $overseer) {
    $this->overseer = $overseer;
    return $this;
  }

  final public function getOverseer() {
    return $this->overseer;
  }

  public static function getAllUnitEngines() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getUnitEngineType')
      ->execute();
  }

  abstract public function runTests();

  final protected function didRunTests(array $tests) {
    assert_instances_of($tests, 'ArcanistUnitTestResult');

    // TOOLSETS: Pass this stuff to result output so it can print progress or
    // stream results.

    foreach ($tests as $test) {
      echo "Ran Test: ".$test->getNamespace().'::'.$test->getName()."\n";
    }
  }

}