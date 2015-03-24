<?php

/**
 * Uses Cppcheck to do basic checks in a C++ file.
 */
final class ArcanistCppcheckLinter extends ArcanistExternalLinter {

  public function getInfoName() {
    return 'Cppcheck';
  }

  public function getInfoURI() {
    return 'http://cppcheck.sourceforge.net';
  }

  public function getInfoDescription() {
    return pht('Use `cppcheck` to perform static analysis on C/C++ code.');
  }

  public function getLinterName() {
    return 'cppcheck';
  }

  public function getLinterConfigurationName() {
    return 'cppcheck';
  }

  public function getDefaultBinary() {
    $prefix = $this->getDeprecatedConfiguration('lint.cppcheck.prefix');
    $bin = $this->getDeprecatedConfiguration('lint.cppcheck.bin', 'cppcheck');

    if ($prefix) {
      return $prefix.'/'.$bin;
    } else {
      return $bin;
    }
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
    return pht('Install Cppcheck using `apt-get install cppcheck` or similar.');
  }

  protected function getMandatoryFlags() {
    return array(
      '--quiet',
      '--inline-suppr',
      '--xml',
      '--xml-version=2',
    );
  }

  protected function getDefaultFlags() {
    return $this->getDeprecatedConfiguration(
      'lint.cppcheck.options',
      array('-j2', '--enable=performance,style,portability,information'));
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
