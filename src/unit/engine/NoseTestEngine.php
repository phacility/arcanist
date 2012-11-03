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

    if (empty($affected_tests)) {
      return array();
    }

    $futures = array();
    $tmpfiles = array();
    foreach ($affected_tests as $test_path) {
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

      $results[] = $this->parseTestResults($test_path,
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

  public function parseTestResults($path, $xunit_tmp, $cover_tmp) {
    // xunit xsd: https://gist.github.com/959290
    $xunit_dom = new DOMDocument();
    $xunit_dom->loadXML(Filesystem::readFile($xunit_tmp));

    // coverage is for all testcases in the executed $path
    $coverage = array();
    if ($this->getEnableCoverage() !== false) {
      $coverage = $this->readCoverage($cover_tmp);
    }

    $results = array();
    $testcases = $xunit_dom->getElementsByTagName("testcase");
    foreach ($testcases as $testcase) {
      $classname = $testcase->getAttribute("classname");
      $name = $testcase->getAttribute("name");
      $time = $testcase->getAttribute("time");

      $status = ArcanistUnitTestResult::RESULT_PASS;
      $user_data = "";

      // A skipped test is a test which was ignored using framework
      // mechanizms (e.g. @skip decorator)
      $skipped = $testcase->getElementsByTagName("skipped");
      if ($skipped->length > 0) {
        $status = ArcanistUnitTestResult::RESULT_SKIP;
        $messages = array();
        for ($ii = 0; $ii < $skipped->length; $ii++) {
          $messages[] = trim($skipped->item($ii)->nodeValue, " \n");
        }

        $user_data .= implode("\n", $messages);
      }

      // Failure is a test which the code has explicitly failed by using
      // the mechanizms for that purpose. e.g., via an assertEquals
      $failures = $testcase->getElementsByTagName("failure");
      if ($failures->length > 0) {
        $status = ArcanistUnitTestResult::RESULT_FAIL;
        $messages = array();
        for ($ii = 0; $ii < $failures->length; $ii++) {
          $messages[] = trim($failures->item($ii)->nodeValue, " \n");
        }

        $user_data .= implode("\n", $messages)."\n";
      }

      // An errored test is one that had an unanticipated problem. e.g., an
      // unchecked throwable, or a problem with an implementation of the
      // test.
      $errors = $testcase->getElementsByTagName("error");
      if ($errors->length > 0) {
        $status = ArcanistUnitTestResult::RESULT_BROKEN;
        $messages = array();
        for ($ii = 0; $ii < $errors->length; $ii++) {
          $messages[] = trim($errors->item($ii)->nodeValue, " \n");
        }

        $user_data .= implode("\n", $messages)."\n";
      }

      $result = new ArcanistUnitTestResult();
      $result->setName($classname.".".$name);
      $result->setResult($status);
      $result->setDuration($time);
      $result->setCoverage($coverage);
      $result->setUserData($user_data);

      $results[] = $result;
    }

    return $results;
  }

  public function readCoverage($path) {
    $coverage_dom = new DOMDocument();
    $coverage_dom->loadXML(Filesystem::readFile($path));

    $reports = array();
    $classes = $coverage_dom->getElementsByTagName("class");

    foreach ($classes as $class) {
      // filename is actually python module path with ".py" at the end,
      // e.g.: tornado.web.py
      $relative_path = explode(".", $class->getAttribute("filename"));
      array_pop($relative_path);
      $relative_path = implode("/", $relative_path);

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
        }
        else if (intval($line->getAttribute("hits")) > 0) {
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
