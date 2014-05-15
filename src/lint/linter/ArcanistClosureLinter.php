<?php

/**
 * Uses gJSLint to detect errors and potential problems in JavaScript code.
 */
final class ArcanistClosureLinter extends ArcanistExternalLinter {

  public function getInfoName() {
    return 'Closure Linter';
  }

  public function getInfoURI() {
    return 'https://developers.google.com/closure/utilities/';
  }

  public function getInfoDescription() {
    return pht(
      'Uses Google\'s Closure Linter to check Javascript code.');
  }

  public function getLinterName() {
    return 'Closure Linter';
  }

  public function getLinterConfigurationName() {
    return 'gjslint';
  }

  protected function getDefaultMessageSeverity($code) {
    return ArcanistLintSeverity::SEVERITY_ERROR;
  }

  public function getDefaultBinary() {
    return 'gjslint';
  }

  public function getInstallInstructions() {
    return pht('Install gJSLint using `sudo easy_install http://closure-linter'.
      '.googlecode.com/files/closure_linter-latest.tar.gz`');
  }

  public function shouldExpectCommandErrors() {
    return true;
  }

  public function supportsReadDataFromStdin() {
    return false;
  }

  protected function parseLinterOutput($path, $err, $stdout, $stderr) {
    // Each line looks like this:
    // Line 46, E:0110: Line too long (87 characters).
    $regex = '/^Line (\d+), (E:\d+): (.*)/';
    $severity_code = ArcanistLintSeverity::SEVERITY_ERROR;

    $lines = explode("\n", $stdout);

    $messages = array();
    foreach ($lines as $line) {
      $line = trim($line);
      $matches = null;
      if (!preg_match($regex, $line, $matches)) {
        continue;
      }
      foreach ($matches as $key => $match) {
        $matches[$key] = trim($match);
      }

      $message = new ArcanistLintMessage();
      $message->setPath($path);
      $message->setLine($matches[1]);
      $message->setName($matches[2]);
      $message->setCode($this->getLinterName());
      $message->setDescription($matches[3]);
      $message->setSeverity($severity_code);

      $messages[] = $message;
    }

    return $messages;
  }
}
