<?php

/**
 * Uses "flake8" to detect various errors in Python code.
 * Requires version 1.7.0 or newer of flake8.
 */
final class ArcanistFlake8Linter extends ArcanistExternalLinter {

  public function getInfoName() {
    return 'Python Flake8 multi-linter';
  }

  public function getInfoURI() {
    return 'https://pypi.python.org/pypi/flake8';
  }

  public function getInfoDescription() {
    return pht(
      'Uses `%s` to run several linters (PyFlakes, pep8, and a McCabe '.
      'complexity checker) on Python source files.',
      'flake8');
  }

  public function getLinterName() {
    return 'flake8';
  }

  public function getLinterConfigurationName() {
    return 'flake8';
  }

  public function getDefaultBinary() {
    return 'flake8';
  }

  public function getVersion() {
    list($stdout) = execx('%C --version', $this->getExecutableCommand());

    $matches = array();
    if (preg_match('/^(?P<version>\d+\.\d+(?:\.\d+)?)\b/', $stdout, $matches)) {
      return $matches['version'];
    } else {
      return false;
    }
  }

  public function getInstallInstructions() {
    return pht('Install flake8 using `%s`.', 'easy_install flake8');
  }

  protected function parseLinterOutput($path, $err, $stdout, $stderr) {
    $lines = phutil_split_lines($stdout, false);

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

      $message = new ArcanistLintMessage();
      $message->setPath($path);
      $message->setLine($matches[2]);
      if (!empty($matches[3])) {
        $message->setChar($matches[3]);
      }
      $message->setCode($matches[4]);
      $message->setName($this->getLinterName().' '.$matches[3]);
      $message->setDescription($matches[5]);
      $message->setSeverity($this->getLintMessageSeverity($matches[4]));

      $messages[] = $message;
    }

    return $messages;
  }

  protected function getDefaultMessageSeverity($code) {
    if (preg_match('/^C/', $code)) {
      // "C": Cyclomatic complexity
      return ArcanistLintSeverity::SEVERITY_ADVICE;
    } else if (preg_match('/^W/', $code)) {
      // "W": PEP8 Warning
      return ArcanistLintSeverity::SEVERITY_WARNING;
    } else {
      // "E": PEP8 Error
      // "F": PyFlakes Error
      return ArcanistLintSeverity::SEVERITY_ERROR;
    }
  }

  protected function getLintCodeFromLinterConfigurationKey($code) {
    if (!preg_match('/^(E|W|C|F)\d+$/', $code)) {
      throw new Exception(
        pht(
          'Unrecognized lint message code "%s". Expected a valid flake8 '.
          'lint code like "%s", or "%s", or "%s", or "%s".',
          $code,
          'E225',
          'W291',
          'F811',
          'C901'));
    }

    return $code;
  }

}
