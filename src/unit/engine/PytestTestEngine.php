<?php

/**
 * Very basic 'py.test' unit test engine wrapper.
 */
final class PytestTestEngine extends ArcanistUnitTestEngine {

  private $projectRoot;

  public function run() {
    $working_copy = $this->getWorkingCopy();
    $this->projectRoot = $working_copy->getProjectRoot();

    $junit_tmp = new TempFile();
    $cover_tmp = new TempFile();

    $future = $this->buildTestFuture($junit_tmp, $cover_tmp);
    list($err, $stdout, $stderr) = $future->resolve();

    if (!Filesystem::pathExists($junit_tmp)) {
      throw new CommandException(
        pht('Command failed with error #%s!', $err),
        $future->getCommand(),
        $err,
        $stdout,
        $stderr);
    }

    $future = new ExecFuture('coverage xml -o %s', $cover_tmp);
    $future->setCWD($this->projectRoot);
    $future->resolvex();

    return $this->parseTestResults($junit_tmp, $cover_tmp);
  }

  public function buildTestFuture($junit_tmp, $cover_tmp) {
    $paths = $this->getPaths();

    $cmd_line = csprintf('py.test --junit-xml=%s', $junit_tmp);

    if ($this->getEnableCoverage() !== false) {
      $cmd_line = csprintf(
        'coverage run --source %s -m %C',
        $this->projectRoot,
        $cmd_line);
    }

    return new ExecFuture('%C', $cmd_line);
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
      // filename is actually python module path with ".py" at the end,
      // e.g.: tornado.web.py
      $relative_path = explode('.', $class->getAttribute('filename'));
      array_pop($relative_path);
      $relative_path = implode('/', $relative_path);

      // first we check if the path is a directory (a Python package), if it is
      // set relative and absolute paths to have __init__.py at the end.
      $absolute_path = Filesystem::resolvePath($relative_path);
      if (is_dir($absolute_path)) {
        $relative_path .= '/__init__.py';
        $absolute_path .= '/__init__.py';
      }

      // then we check if the path with ".py" at the end is file (a Python
      // submodule), if it is - set relative and absolute paths to have
      // ".py" at the end.
      if (is_file($absolute_path.'.py')) {
        $relative_path .= '.py';
        $absolute_path .= '.py';
      }

      if (!file_exists($absolute_path)) {
        continue;
      }

      if (!in_array($relative_path, $paths)) {
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
        } else if (intval($line->getAttribute('hits')) > 0) {
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
