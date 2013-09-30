<?php

/**
 * Very basic 'nose' unit test engine wrapper.
 *
 * Requires nose 1.1.3 for code coverage.
 *
 * @group unitrun
 */
final class NoseTestEngine extends ArcanistBaseUnitTestEngine {

  public function run() {

    $paths = $this->getPaths();

    $affected_tests = array();
    foreach ($paths as $path) {
      $absolute_path = Filesystem::resolvePath($path);

      if (is_dir($absolute_path)) {
        $absolute_test_path = Filesystem::resolvePath("tests/".$path);
        if (is_readable($absolute_test_path)) {
          $affected_tests[] = $absolute_test_path;
        }
      }

      if (is_readable($absolute_path)) {
        $filename = basename($path);
        $directory = dirname($path);

        // assumes directory layout: tests/<package>/test_<module>.py
        $relative_test_path = "tests/".$directory."/test_".$filename;
        $absolute_test_path = Filesystem::resolvePath($relative_test_path);

        if (is_readable($absolute_test_path)) {
          $affected_tests[] = $absolute_test_path;
        }
      }
    }

    return $this->runTests($affected_tests, './');
  }

  public function runTests($test_paths, $source_path) {
    if (empty($test_paths)) {
      return array();
    }

    $futures = array();
    $tmpfiles = array();
    foreach ($test_paths as $test_path) {
      $xunit_tmp = new TempFile();
      $cover_tmp = new TempFile();

      $future = $this->buildTestFuture($test_path,
                                       $xunit_tmp,
                                       $cover_tmp);

      $futures[$test_path] = $future;
      $tmpfiles[$test_path] = array(
        'xunit' => $xunit_tmp,
        'cover' => $cover_tmp,
      );
    }

    $results = array();
    foreach (Futures($futures)->limit(4) as $test_path => $future) {
      try {
        list($stdout, $stderr) = $future->resolvex();
      } catch(CommandException $exc) {
        if ($exc->getError() > 1) {
          // 'nose' returns 1 when tests are failing/broken.
          throw $exc;
        }
      }

      $xunit_tmp = $tmpfiles[$test_path]['xunit'];
      $cover_tmp = $tmpfiles[$test_path]['cover'];

      $this->parser = new ArcanistXUnitTestResultParser();
      $results[] = $this->parseTestResults($source_path,
                                           $xunit_tmp,
                                           $cover_tmp);
    }

    return array_mergev($results);
  }

  public function buildTestFuture($path, $xunit_tmp, $cover_tmp) {
    $cmd_line = csprintf("nosetests --with-xunit --xunit-file=%s",
                         $xunit_tmp);

    if ($this->getEnableCoverage() !== false) {
      $cmd_line .= csprintf(" --with-coverage --cover-xml " .
                            "--cover-xml-file=%s",
                            $cover_tmp);
    }

    return new ExecFuture("%C %s", $cmd_line, $path);
  }

  public function parseTestResults($source_path, $xunit_tmp, $cover_tmp) {
    $results = $this->parser->parseTestResults(
      Filesystem::readFile($xunit_tmp));

    // coverage is for all testcases in the executed $path
    if ($this->getEnableCoverage() !== false) {
      $coverage = $this->readCoverage($cover_tmp, $source_path);
      foreach ($results as $result) {
        $result->setCoverage($coverage);
      }
    }

    return $results;
  }

  public function readCoverage($cover_file, $source_path) {
    $coverage_dom = new DOMDocument();
    $coverage_dom->loadXML(Filesystem::readFile($cover_file));

    $reports = array();
    $classes = $coverage_dom->getElementsByTagName("class");

    foreach ($classes as $class) {
      // filename is actually python module path with ".py" at the end,
      // e.g.: tornado.web.py
      $relative_path = explode(".", $class->getAttribute("filename"));
      array_pop($relative_path);
      $relative_path = $source_path .'/'. implode("/", $relative_path);
      $relative_path = Filesystem::readablePath($relative_path);

      // first we check if the path is a directory (a Python package), if it is
      // set relative and absolute paths to have __init__.py at the end.
      $absolute_path = Filesystem::resolvePath($relative_path);
      if (is_dir($absolute_path)) {
        $relative_path .= "/__init__.py";
        $absolute_path .= "/__init__.py";
      }

      // then we check if the path with ".py" at the end is file (a Python
      // submodule), if it is - set relative and absolute paths to have
      // ".py" at the end.
      if (is_file($absolute_path.".py")) {
        $relative_path .= ".py";
        $absolute_path .= ".py";
      }

      if (!file_exists($absolute_path)) {
        continue;
      }

      // get total line count in file
      $line_count = count(file($absolute_path));

      $coverage = "";
      $start_line = 1;
      $lines = $class->getElementsByTagName("line");
      for ($ii = 0; $ii < $lines->length; $ii++) {
        $line = $lines->item($ii);

        $next_line = intval($line->getAttribute("number"));
        for ($start_line; $start_line < $next_line; $start_line++) {
          $coverage .= "N";
        }

        if (intval($line->getAttribute("hits")) == 0) {
          $coverage .= "U";
        } else if (intval($line->getAttribute("hits")) > 0) {
          $coverage .= "C";
        }

        $start_line++;
      }

      if ($start_line < $line_count) {
        foreach (range($start_line, $line_count) as $line_num) {
          $coverage .= "N";
        }
      }

      $reports[$relative_path] = $coverage;
    }

    return $reports;
  }

}
