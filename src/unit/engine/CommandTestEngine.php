<?php

/**
 * Very basic child process unit test engine wrapper.
 *
 * Set `'unit.engine.command'` in your `.arcconfig` and this
 *  test runner will execute that command as a child process
 *
 * If the command exits with a non-zero exit code it's
 *  considered a test failure and stdout is printed. Otherwise
 *  if the command exits 0 its a successful unit test run
 *
 * @group unitrun
 */
final class CommandTestEngine extends ArcanistBaseUnitTestEngine {

  public function run() {
    $results = array();
    $build_start = microtime(true);
    $config_manager = $this->getConfigurationManager();
    $command = $config_manager
        ->getConfigFromAnySource('unit.engine.command');
    $future = new ExecFuture('%C', $command);
    $result = new ArcanistUnitTestResult();
    $result->setName($command);

    try {
        list($stdout, $stderr) = $future->resolvex();
        $result->setResult(ArcanistUnitTestResult::RESULT_PASS);
    } catch(CommandException $exc) {
        $result->setResult(ArcanistUnitTestResult::RESULT_FAIL);
        $result->setUserdata($exc->getStdout());
    }

    $result->setDuration(microtime(true) - $build_start);
    $results[] = $result;
    return $results;
  }

}
