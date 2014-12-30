<?php

/**
 * Uses "php -l" to detect syntax errors in PHP code.
 */
final class ArcanistPhpLinter extends ArcanistExternalLinter {

  public function getInfoName() {
    return 'php -l';
  }

  public function getInfoURI() {
    return 'http://php.net/';
  }

  public function getInfoDescription() {
    return pht(
      'Checks for syntax errors in php files.');
  }

  public function getLinterName() {
    return 'PHP';
  }

  public function getLinterConfigurationName() {
    return 'php';
  }

  public function getMandatoryFlags() {
    return array('-l');
  }

  public function getInstallInstructions() {
    return pht('Install PHP.');
  }

  public function getDefaultBinary() {
    return 'php';
  }

  public function getVersion() {
    list($stdout) = execx('%C --version', $this->getExecutableCommand());

    $matches = array();
    $regex = '/^PHP (?P<version>\d+\.\d+\.\d+)\b/';
    if (preg_match($regex, $stdout, $matches)) {
      return $matches['version'];
    } else {
      return false;
    }
  }

  public function shouldExpectCommandErrors() {
    return true;
  }

  public function supportsReadDataFromStdin() {
    return false;
  }

  protected function parseLinterOutput($path, $err, $stdout, $stderr) {
    // Older versions of php had both on $stdout, newer ones split it
    // Combine $stdout and $stderr for consistency
    $stdout = $stderr."\n".$stdout;
    $matches = array();
    $regex = '/^(?<type>.+?) error:\s+(?<error>.*?)\s+in\s+(?<file>.*?)'.
      '\s+on line\s+(?<line>\d*)$/m';
    if (preg_match($regex, $stdout, $matches)) {
      $type = strtolower($matches['type']);
      $message = new ArcanistLintMessage();
      $message->setPath($matches['file']);
      $message->setLine($matches['line']);
      $message->setCode('php.'.$type);
      $message->setDescription('This file contains a '.$type.' error: '.
        $matches['error'].' on line '.$matches['line']);
      $message->setSeverity(ArcanistLintSeverity::SEVERITY_ERROR);

      // php -l only returns the first error
      return array($message);
    }

    return array();
  }

}
