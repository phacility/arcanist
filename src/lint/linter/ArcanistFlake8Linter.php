<?php

/**
 * Uses "flake8" to detect various errors in Python code.
 * Requires version 1.7.0 or newer of flake8.
 *
 * @group linter
 */
final class ArcanistFlake8Linter extends ArcanistExternalLinter {

  public function getLinterName() {
    return 'flake8';
  }

  public function getDefaultFlags() {
    // TODO: Deprecated.

    $working_copy = $this->getEngine()->getWorkingCopy();

    $options = $working_copy->getConfig('lint.flake8.options', '');

    return $options;
  }

  public function getDefaultBinary() {
    $working_copy = $this->getEngine()->getWorkingCopy();
    $prefix = $working_copy->getConfig('lint.flake8.prefix');
    $bin = $working_copy->getConfig('lint.flake8.bin', 'flake8');

    if ($prefix || ($bin != 'flake8')) {
      return $prefix.'/'.$bin;
    }

    return 'flake8';
  }

  public function getInstallInstructions() {
    return pht('Install flake8 using `easy_install flake8`.');
  }

  public function supportsReadDataFromStdin() {
    return true;
  }

  public function getReadDataFromStdinFilename() {
    return '-';
  }

  public function shouldExpectCommandErrors() {
    return true;
  }

  protected function parseLinterOutput($path, $err, $stdout, $stderr) {
    $lines = phutil_split_lines($stdout, $retain_endings = false);

    $messages = array();
    foreach ($lines as $line) {
      $matches = null;
      // stdin:2: W802 undefined name 'foo'  # pyflakes
      // stdin:3:1: E302 expected 2 blank lines, found 1  # pep8
      $regexp = '/^(.*?):(\d+):(?:(\d+):)? (\S+) (.*)$/';
      if (!preg_match($regexp, $line, $matches)) {
        continue;
      }
      foreach ($matches as $key => $match) {
        $matches[$key] = trim($match);
      }

      if (substr($matches[4], 0, 1) == 'E') {
        $severity = ArcanistLintSeverity::SEVERITY_ERROR;
      } else {
        $severity = ArcanistLintSeverity::SEVERITY_WARNING;
      }

      $message = new ArcanistLintMessage();
      $message->setPath($path);
      $message->setLine($matches[2]);
      if (!empty($matches[3])) {
        $message->setChar($matches[3]);
      }
      $message->setCode($matches[4]);
      $message->setName($this->getLinterName().' '.$matches[3]);
      $message->setDescription($matches[5]);
      $message->setSeverity($severity);

      $messages[] = $message;
    }

    if ($err && !$messages) {
      return false;
    }

    return $messages;
  }

}
