<?php

final class ConfigurableGolangTestEngine extends ArcanistUnitTestEngine {

  public function run() {
    $working_copy = $this->getWorkingCopy();
    $this->project_root = $working_copy->getProjectRoot();

    $cover_tmp = new TempFile();
    $junit_tmp = new TempFile();

    $future = $this->buildTestFuture($junit_tmp, $cover_tmp);
    $future->resolvex();

    return $this->parseTestResults($junit_tmp, $cover_tmp);
  }

  public function buildTestFuture($junit_tmp, $cover_tmp) {
    $paths = $this->getPaths();
    $config_manager = $this->getConfigurationManager();
    $coverage_command = $config_manager
      ->getConfigFromAnySource('unit.golang.command');
    $cmd_line = csprintf($coverage_command, $junit_tmp, $cover_tmp);

    $future = new ExecFuture('%C', $cmd_line);
    $future->setCWD($this->project_root);
    return $future;
  }

  public function parseTestResults($junit_tmp, $cover_tmp) {
    $parser = new ArcanistXUnitTestResultParser();
    $results = $parser->parseTestResults(
      Filesystem::readFile($junit_tmp));

    if ($this->getEnableCoverage() !== false) {
      $coverage_report = $this->readCoverage($cover_tmp);
      foreach ($results as $result) {
          $result->setCoverage($coverage_report);
      }
    }

    return $results;
  }

  public function readCoverage($path) {
    $coverage_data = Filesystem::readFile($path);
    if (empty($coverage_data)) {
       return array();
    }

    $coverage_dom = new DOMDocument();
    $coverage_dom->loadXML($coverage_data);

    $paths = $this->getPaths();
    $reports = array();
    $classes = $coverage_dom->getElementsByTagName('class');

    foreach ($classes as $class) {
      $absolute_path = $class->getAttribute('filename');
      $relative_path = str_replace($this->project_root.'/', '', $absolute_path);

      if (is_file($absolute_path.'.go')) {
        $relative_path .= '.go';
        $absolute_path .= '.go';
      }

      if (!file_exists($absolute_path)) {
        continue;
      }

      // get total line count in file
      $line_count = count(file($absolute_path));

      $coverage = '';
      $start_line = 1;
      $lines = $class->getElementsByTagName('line');
      for ($ii = 0; $ii < $lines->length; $ii++) {
        $line = $lines->item($ii);

        $next_line = intval($line->getAttribute('number'));
        for ($start_line; $start_line < $next_line; $start_line++) {
            $coverage .= 'N';
        }

        if (intval($line->getAttribute('hits')) == 0) {
            $coverage .= 'U';
        }
        else if (intval($line->getAttribute('hits')) > 0) {
            $coverage .= 'C';
        }

        $start_line++;
      }

      if ($start_line < $line_count) {
        foreach (range($start_line, $line_count) as $line_num) {
          $coverage .= 'N';
        }
      }

      $reports[$relative_path] = $coverage;
    }

    return $reports;
  }

}
