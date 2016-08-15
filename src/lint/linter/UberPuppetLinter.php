<?php

/**
 * Runs puppet parser validate for Puppet files.
 */
final class ArcanistUberPuppetLinter extends ArcanistExternalLinter {

  private $config;

  public function getInfoName() {
    return 'puppet parser validate';
  }

  public function getInfoURI() {
    return 'https://docs.puppetlabs.com/references/';
  }

  public function getInfoDescription() {
    return pht(
      'Validates Puppet DSL syntax without compiling a catalog or'.
      'syncing any resources.',
      'uber-puppet');
  }

  public function getLinterName() {
    return 'UBER-PUPPET';
  }

  public function getLinterConfigurationName() {
    return 'uber-puppet';
  }

  public function getDefaultBinary() {
    return 'puppet';
  }

  protected function getMandatoryFlags() {
    return array('parser', 'validate');
  }

  public function getVersion() {
    list($stdout) = execx('%C --version', $this->getExecutableCommand());

    $matches = array();
    $regex = '/^(?P<version>\d+\.\d+\.\d+)$/';
    if (preg_match($regex, $stdout, $matches)) {
      return $matches['version'];
    } else {
      return false;
    }
  }

  public function getInstallInstructions() {
    return pht('Run `update-uber-home.sh` or install puppet using `apt-get install puppet` or similar.');
  }

  protected function parseLinterOutput($path, $err, $stdout, $stderr) {

    if (!$err) {
      return array();
    }

    $lines = phutil_split_lines($stderr, false);
    $messages = array();

    foreach ($lines as $line) {
      $matches = null;
      $regexp = '/Error: (.*)at (.*):(\d+)/';
      $match = preg_match($regexp, $line, $matches);

      if ($match) {
        $message = id(new ArcanistLintMessage())
          ->setDescription($matches[1])
          ->setLine($matches[3])
          ->setPath($matches[2])
          ->setSeverity(ArcanistLintSeverity::SEVERITY_ERROR)
          ->setCode($this->getLinterName())
          ->setName('Puppet Error');
        $messages[] = $message;
      }
    }

    return $messages;
  }
}
