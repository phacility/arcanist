<?php

final class ArcanistGoVetLinter extends ArcanistExternalLinter {

  public function getInfoName() {
    return 'Go Vet';
  }

  public function getInfoURI() {
    return 'https://godoc.org/golang.org/x/tools/cmd/vet';
  }

  public function getInfoDescription() {
    return pht(
      'Vet examines Go source code and reports suspicious constructs.');
  }

  public function getLinterName() {
    return 'GOVET';
  }

  public function getLinterConfigurationName() {
    return 'govet';
  }

  public function getDefaultBinary() {
    $binary = 'go';
    if (Filesystem::binaryExists($binary)) {
      // Vet is only accessible through 'go vet'
      // Let's manually try to find out if it's installed.
      list($err, $stdout, $stderr) = exec_manual('go vet');
      if ($err === 3) {
        throw new ArcanistMissingLinterException(
          sprintf(
            "%s\n%s",
            pht(
              'Unable to locate "go vet" to run linter %s. You may need '.
              'to install the binary, or adjust your linter configuration.',
              get_class($this)),
            pht(
              'TO INSTALL: %s',
              $this->getInstallInstructions())));
      }
    }

    return $binary;
  }

  public function getInstallInstructions() {
    return pht(
      'Install Go vet using `%s`.',
      'go get golang.org/x/tools/cmd/vet');
  }

  protected function getMandatoryFlags() {
    return array('tool', 'vet');
  }

  public function getVersion() {
    list($stdout) = execx('%C version', $this->getExecutableCommand());

    $pattern = '/^go version go(?P<version>\d+\.\d+\.\d+).*$/';
    $matches = array();
    if (preg_match($pattern, $stdout, $matches)) {
      return $matches['version'];
    } else {
      return false;
    }
  }

  protected function parseLinterOutput($path, $err, $stdout, $stderr) {
    $lines = phutil_split_lines($stderr, false);

    $messages = array();
    foreach ($lines as $line) {
      preg_match('/[^:]*:([0-9]+)(:[0-9]+)?: (.*)/', $line, $matches);

      if (count($matches) === 4) {
        $message = new ArcanistLintMessage();
        $message->setPath($path);
        $message->setLine($matches[1]);
        $message->setCode($this->getLinterName());
        $message->setName($this->getLinterName());
        $message->setDescription(ucfirst(trim($matches[3])));
        $message->setSeverity(ArcanistLintSeverity::SEVERITY_WARNING);

        $messages[] = $message;
      }
    }

    return $messages;
  }

}
