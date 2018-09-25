<?php

final class ArcanistUnitOverseer
  extends Phobject {

  private $directory;
  private $paths = array();
  private $formatter;

  public function setPaths($paths) {
    $this->paths = $paths;
    return $this;
  }

  public function getPaths() {
    return $this->paths;
  }

  public function setFormatter(ArcanistUnitFormatter $formatter) {
    $this->formatter = $formatter;
    return $this;
  }

  public function getFormatter() {
    return $this->formatter;
  }

  public function setDirectory($directory) {
    $this->directory = $directory;
    return $this;
  }

  public function getDirectory() {
    return $this->directory;
  }

  public function execute() {
    $engines = $this->loadEngines();

    foreach ($engines as $engine) {
      $engine->setOverseer($this);
    }

    $results = array();

    foreach ($engines as $engine) {
      $tests = $engine->runTests();
      foreach ($tests as $test) {
        $results[] = $test;
      }
    }

    return $results;
  }

  private function loadEngines() {
    $root = $this->getDirectory();

    $arcunit_path = Filesystem::concatenatePaths(array($root, '.arcunit'));
    $arcunit_display = Filesystem::readablePath($arcunit_path);

    if (!Filesystem::pathExists($arcunit_path)) {
      throw new Exception(
        pht(
          'No ".arcunit" file exists at path "%s". Create an ".arcunit" file '.
          'to define how "arc unit" should run tests.',
          $arcunit_display));
    }

    try {
      $data = Filesystem::readFile($arcunit_path);
    } catch (Exception $ex) {
      throw new PhutilProxyException(
        pht(
          'Failed to read ".arcunit" file (at path "%s").',
          $arcunit_display),
        $ex);
    }

    try {
      $spec = phutil_json_decode($data);
    } catch (PhutilJSONParserException $ex) {
      throw new PhutilProxyException(
        pht(
          'Expected ".arcunit" file (at path "%s") to be a valid JSON file, '.
          'but it could not be parsed.',
          $arcunit_display),
        $ex);
    }

    try {
      PhutilTypeSpec::checkMap(
        $spec,
        array(
          'engines' => 'map<string, wild>',
        ));
    } catch (PhutilTypeCheckException $ex) {
      throw new PhutilProxyException(
        pht(
          'The ".arcunit" file (at path "%s") is not formatted correctly.',
          $arcunit_display),
        $ex);
    }

    $all_engines = ArcanistUnitEngine::getAllUnitEngines();

    $engines = array();
    foreach ($spec['engines'] as $key => $engine_spec) {
      try {
        PhutilTypeSpec::checkMap(
          $engine_spec,
          array(
            'type' => 'string',
            'include' => 'optional regex | list<regex>',
            'exclude' => 'optional regex | list<regex>',
          ));
      } catch (PhutilTypeCheckException $ex) {
        throw new PhutilProxyException(
          pht(
            'The ".arcunit" file (at path "%s") is not formatted correctly: '.
            'the engine with key "%s" is specified improperly.',
            $arcunit_display,
            $key));
      }

      $type = $engine_spec['type'];
      if (!isset($all_engines[$type])) {
        throw new Exception(
          pht(
            'The ".arcunit" file (at path "%s") specifies an engine (with '.
            'key "%s") of an unknown type ("%s").',
            $arcunit_display,
            $key,
            $type));
      }

      $engine = clone $all_engines[$type];

      if (isset($engine_spec['include'])) {
        $engine->setIncludePaths((array)$engine_spec['include']);
      }

      if (isset($engine_spec['exclude'])) {
        $engine->setExcludePaths((array)$engine_spec['exclude']);
      }

      $engines[] = $engine;
    }

    return $engines;
  }

}
