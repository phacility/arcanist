<?php

abstract class UberConfigurableTestEngine extends ArcanistUnitTestEngine {

  /**
   * Try retrieve and validate coverage command from .arconfig.
   *
   * Coverage command must be set and must have two "%s" placeholders for
   * sprintf. Exception is thrown if command is not valid.
   */
  protected function getCoverageCommand($name) {
    $config_manager = $this->getConfigurationManager();
    $coverage_command = $config_manager
      ->getConfigFromAnySource($name);
    if ('' == $coverage_command) {
      $message = sprintf(
        'Config option "%s" required for %s is missing. '.
        'Set value in .arcconfig file.',
        $name, get_class($this));
      throw new Exception($message);
    }

    if (2 != preg_match_all('/%s/', $coverage_command)) {
      $message = sprintf(
        'Config option "%s" must have two "%%s" placeholders. The first is '.
        'used for JUNIT_XML. The second for COVERAGE_XML. '.PHP_EOL.
        'Example: '.PHP_EOL.
        '  make clean && make -j8 test-xml JUNIT_XML=%%s COVERAGE_XML=%%s'.
        PHP_EOL, $name);
      throw new Exception($message);
    }

    return $coverage_command;
  }
}
