<?php

final class ArcanistGoLintLinter extends ArcanistExternalLinter {

  public function getInfoName() {
    return 'Golint';
  }

  public function getInfoURI() {
    return 'https://github.com/golang/lint';
  }

  public function getInfoDescription() {
    return pht('Golint is a linter for Go source code.');
  }

  public function getLinterName() {
    return 'GOLINT';
  }

  public function getLinterConfigurationName() {
    return 'golint';
  }

  public function getDefaultBinary() {
    return 'golint';
  }

  public function getInstallInstructions() {
    return pht(
      'Install Golint using `%s`.',
      'go get -u golang.org/x/lint/golint');
  }

  public function shouldExpectCommandErrors() {
    return false;
  }

  protected function canCustomizeLintSeverities() {
    return true;
  }

  protected function parseLinterOutput($path, $err, $stdout, $stderr) {
    $lines = phutil_split_lines($stdout, false);

    $messages = array();
    foreach ($lines as $line) {
      $matches = explode(':', $line, 4);

      if (count($matches) === 4) {
        $message = new ArcanistLintMessage();
        $message->setPath($path);
        $message->setLine($matches[1]);
        $message->setChar($matches[2]);
        $message->setCode($this->getLinterName());
        $message->setName($this->getLinterName());
        $message->setDescription(ucfirst(trim($matches[3])));
        $message->setSeverity(ArcanistLintSeverity::SEVERITY_ADVICE);

        $messages[] = $message;
      }
    }

    return $messages;
  }

}
