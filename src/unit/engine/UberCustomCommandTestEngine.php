<?php
final class UberCustomCommandTestEngine extends ArcanistUnitTestEngine {
  public function run() {
    $results = array();
    $build_start = microtime(true);
    $config_manager = $this->getConfigurationManager();
    $command = $config_manager->getConfigFromAnySource('unit.engine.command');
    $timeout = $config_manager->getConfigFromAnySource('unit.engine.timeout');

    $future = new ExecFuture('%C', $command);
    $future->setTimeout($timeout);
    $result = new ArcanistUnitTestResult();
    $result->setName($command);

    try {
      list($stdout, $stderr) = $future->resolvex();
      $result->setResult(ArcanistUnitTestResult::RESULT_PASS);
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
}
?>
