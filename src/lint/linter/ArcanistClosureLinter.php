<?php

/**
 * Uses `gjslint` to detect errors and potential problems in JavaScript code.
 */
final class ArcanistClosureLinter extends ArcanistExternalLinter {

  public function getInfoName() {
    return pht('Closure Linter');
  }

  public function getInfoURI() {
    return 'https://developers.google.com/closure/utilities/';
  }

  public function getInfoDescription() {
    return pht("Uses Google's Closure Linter to check JavaScript code.");
  }

  public function getLinterName() {
    return 'GJSLINT';
  }

  public function getLinterConfigurationName() {
    return 'gjslint';
  }

  public function getDefaultBinary() {
    return 'gjslint';
  }

  public function getInstallInstructions() {
    return pht(
      'Install %s using `%s`.',
      'gjslint',
      'sudo easy_install http://closure-linter.googlecode.com/'.
      'files/closure_linter-latest.tar.gz');
  }

  protected function parseLinterOutput($path, $err, $stdout, $stderr) {
    $lines = phutil_split_lines($stdout, false);
    $messages = array();

    foreach ($lines as $line) {
      $matches = null;
      if (!preg_match('/^Line (\d+), E:(\d+): (.*)/', $line, $matches)) {
        continue;
      }

      $message = id(new ArcanistLintMessage())
        ->setPath($path)
        ->setLine($matches[1])
        ->setSeverity(ArcanistLintSeverity::SEVERITY_ERROR)
        ->setCode($this->getLinterName().$matches[2])
        ->setDescription($matches[3]);
      $messages[] = $message;
    }

    return $messages;
  }

}
