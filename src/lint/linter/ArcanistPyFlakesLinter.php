<?php

/**
 * Uses "PyFlakes" to detect various errors in Python code.
 */
final class ArcanistPyFlakesLinter extends ArcanistExternalLinter {

  public function getInfoURI() {
    return 'https://pypi.python.org/pypi/pyflakes';
  }

  public function getInfoName() {
    return pht('Python PyFlakes');
  }

  public function getInfoDescription() {
    return pht(
      'PyFlakes is a simple program which checks Python source files for '.
      'errors.');
  }

  public function getLinterName() {
    return 'PYFLAKES';
  }

  public function getLinterConfigurationName() {
    return 'pyflakes';
  }

  public function getDefaultBinary() {
    return 'pyflakes';
  }

  public function getVersion() {
    list($stdout) = execx('%C --version', $this->getExecutableCommand());

    $matches = array();
    $pattern = '/^(?P<version>\d+\.\d+\.\d+)( Python.*)?$/';
    if (preg_match($pattern, $stdout, $matches)) {
      return $matches['version'];
    } else {
      return false;
    }
  }

  public function getInstallInstructions() {
    return pht('Install pyflakes with `%s`.', 'pip install pyflakes');
  }

  protected function parseLinterOutput($path, $err, $stdout, $stderr) {
    $lines = phutil_split_lines($stdout, false);

    $messages = array();
    foreach ($lines as $line) {
      $matches = null;
      $pattern = '/^(?<path>.*?):(?<line>\d+):(?<column>\d*) (?<message>.*)$/';
      if (!preg_match($pattern, $line, $matches)) {
        continue;
      }
      foreach ($matches as $key => $match) {
        $matches[$key] = trim($match);
      }

      $severity = ArcanistLintSeverity::SEVERITY_WARNING;
      $description = $matches['message'];

      $error_regexp = '/(^undefined|^duplicate|before assignment$)/';
      if (preg_match($error_regexp, $description)) {
        $severity = ArcanistLintSeverity::SEVERITY_ERROR;
      }

      $message = new ArcanistLintMessage();
      $message->setPath($path);
      $message->setLine($matches['line']);
      if ($matches['column'] != '') {
        $message->setChar($matches['column']);
      }
      $message->setCode($this->getLinterName());
      $message->setName($this->getLinterName());
      $message->setDescription($description);
      $message->setSeverity($severity);

      $messages[] = $message;
    }

    return $messages;
  }

  protected function canCustomizeLintSeverities() {
    return false;
  }

}
