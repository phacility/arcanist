<?php

/**
 * Very basic command line test wrapper
 *
 * runs the command as per `'unit.engine.tap.command'` and
 *   fails if you exit non zero for said command
 *
 * @group unitrun
 */
final class TAPTestEngine extends ArcanistUnitTestEngine {

  public function run() {
    $results = array();
    $build_start = microtime(true);
    $config_manager = $this->getConfigurationManager();

    if ($this->getEnableCoverage() !== false) {
        $command = $config_manager
            ->getConfigFromAnySource('unit.engine.tap.cover');
    } else {
        $command = $config_manager
            ->getConfigFromAnySource('unit.engine.tap.command');
    }

    $timeout = $config_manager
        ->getConfigFromAnySource('unit.engine.tap.timeout');

    if (!$timeout) {
        $timeout = 15;
    }

    $future = new ExecFuture('%C', $command);

    $future->setTimeout($timeout);
    $result = new ArcanistUnitTestResult();
    $result->setName($command ? $command : 'unknown');
    $projectRoot = $this->getWorkingCopy()->getProjectRoot();
    $coveragePath = Filesystem::resolvePath('coverage/cobertura-coverage.xml', $projectRoot);
    try {
        list($stdout, $stderr) = $future->resolvex();
        $result->setResult(ArcanistUnitTestResult::RESULT_PASS);
        if ($this->getEnableCoverage() !== false) {
            $coverage = $this->readCoverage($coveragePath);
            $result->setCoverage($coverage);
        }
    } catch(CommandException $exc) {
        $result->setResult(ArcanistUnitTestResult::RESULT_FAIL);

        if ($future->getWasKilledByTimeout()) {
          print(
            "Process stdout:\n" . $exc->getStdout() .
            "\nProcess stderr:\n" . $exc->getStderr() .
            "\nExceeded timeout of $timeout secs.\nMake unit tests faster."
          );
        } else {
          $result->setUserdata($exc->getStdout() . $exc->getStderr());
        }
    }

    $result->setDuration(microtime(true) - $build_start);
    $results[] = $result;
    return $results;
  }

  public function readCoverage($cover_file) {
    $coverage_dom = new DOMDocument();
    $coverage_dom->loadXML(Filesystem::readFile($cover_file));
    $modified = $this->getPaths();

    $reports = array();
    $classes = $coverage_dom->getElementsByTagName("class");

    foreach ($classes as $class) {
      $path = $class->getAttribute("filename");
      $root = $this->getWorkingCopy()->getProjectRoot();

      if (in_array($path, $modified) === false) {
        continue;
      }

      if (!Filesystem::isDescendant($path, $root)) {
        continue;
      }

      // get total line count in file
      $line_count = count(phutil_split_lines(Filesystem::readFile($path)));

      $coverage = "";
      $start_line = 1;
      $lines = $this->unitTestEngineGetLines($class);

      for ($ii = 0; $ii < count($lines); $ii++) {
        $line = $lines[$ii];
        $next_line = $line["number"];

        for ($start_line; $start_line < $next_line; $start_line++) {
          $coverage .= "N";
        }

        if ($line["hits"] === 0) {
          $coverage .= "U";
        } else if ($line["hits"] > 0) {
          $coverage .= "C";
        }

        $start_line++;
      }

      if ($start_line < $line_count) {
        foreach (range($start_line, $line_count) as $line_num) {
          $coverage .= "N";
        }
      }

      $reports[$path] = $coverage;
    }

    return $reports;
  }

  private function unitTestEngineGetLines($class) {
    $lines = $class->getElementsByTagName('line');
    $result = array();

    for ($i = 0; $i < $lines->length; $i++) {
      $item = $lines->item($i);
      $line_info = array(
        'number' => intval($item->getAttribute('number')),
        'hits' => intval($item->getAttribute('hits')),
      );

      array_push($result, $line_info);
    }

    usort($result, array($this, 'unitTestEngineSortLines'));

    return $result;
  }

  private function unitTestEngineSortLines($a, $b) {
      $a_number = $a['number'];
      $b_number = $b['number'];

      if ($a_number === $b_number) {
          return 0;
      }

      return ($a_number < $b_number) ? -1 : 1;
  }
}
