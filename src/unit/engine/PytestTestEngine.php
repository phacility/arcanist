<?php

/**
 * Very basic 'py.test' unit test engine wrapper.
 *
 * @group unitrun
 */
final class PytestTestEngine extends ArcanistBaseUnitTestEngine {

  public function run() {
    $junit_tmp = new TempFile();

    $cmd_line = csprintf('py.test --junitxml=%s',
                         $junit_tmp);
    $future = new ExecFuture('%C', $cmd_line);
    list($stdout, $stderr) = $future->resolvex();

    $parser = new ArcanistXUnitTestResultParser();

    return $parser->parseTestResults(Filesystem::readFile($junit_tmp));
  }

}
