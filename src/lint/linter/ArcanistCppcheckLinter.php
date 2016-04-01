<?php

/**
 * Uses Cppcheck to do basic checks in a C++ file.
 */
final class ArcanistCppcheckLinter extends ArcanistExternalLinter {

  public function getInfoName() {
    return 'C++ linter';
  }

  public function getInfoURI() {
    return 'http://cppcheck.sourceforge.net';
  }

  public function getInfoDescription() {
    return pht(
      'Use `%s` to perform static analysis on C/C++ code.',
      'cppcheck');
  }

  public function getLinterName() {
    return 'cppcheck';
  }

  public function getLinterConfigurationName() {
    return 'cppcheck';
  }

  public function getDefaultBinary() {
    return 'cppcheck';
  }

  public function getVersion() {
    list($stdout) = execx('%C --version', $this->getExecutableCommand());

    $matches = array();
    $regex = '/^Cppcheck (?P<version>\d+\.\d+)$/';
    if (preg_match($regex, $stdout, $matches)) {
      return $matches['version'];
    } else {
      return false;
    }
  }

  public function getInstallInstructions() {
    return pht(
      'Install Cppcheck using `%s` or similar.',
      'apt-get install cppcheck');
  }

  protected function getDefaultFlags() {
    return array(
      '-j2',
      '--enable=performance,style,portability,information',
    );
  }

  protected function getMandatoryFlags() {
    return array(
      '--quiet',
      '--inline-suppr',
      '--xml',
      '--xml-version=2',
    );
  }

  public function shouldExpectCommandErrors() {
    return false;
  }

  protected function parseLinterOutput($path, $err, $stdout, $stderr) {
    $dom = new DOMDocument();
    $ok = @$dom->loadXML($stderr);

    if (!$ok) {
      return false;
    }

    $errors = $dom->getElementsByTagName('error');
    $messages = array();
    foreach ($errors as $error) {
      foreach ($error->getElementsByTagName('location') as $location) {
        $message = new ArcanistLintMessage();
        $message->setPath($location->getAttribute('file'));
        $message->setLine($location->getAttribute('line'));
        $message->setCode('Cppcheck');
        $message->setName($error->getAttribute('id'));
        $message->setDescription($error->getAttribute('msg'));

        switch ($error->getAttribute('severity')) {
          case 'error':
            $message->setSeverity(ArcanistLintSeverity::SEVERITY_ERROR);
            break;

          default:
            if ($error->getAttribute('inconclusive')) {
              $message->setSeverity(ArcanistLintSeverity::SEVERITY_ADVICE);
            } else {
              $message->setSeverity(ArcanistLintSeverity::SEVERITY_WARNING);
            }
            break;
        }

        $messages[] = $message;
      }
    }

    return $messages;
  }

}
