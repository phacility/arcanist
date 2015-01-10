<?php

/**
 * A linter for Puppet files.
 */
final class ArcanistPuppetLintLinter extends ArcanistExternalLinter {

  private $config;

  public function getInfoURI() {
    return 'http://puppet-lint.com/';
  }

  public function getInfoName() {
    return 'puppet-lint';
  }

  public function getInfoDescription() {
    return pht(
      'Use `puppet-lint` to check that your Puppet manifests conform to '.
      'the style guide.');
  }

  public function getLinterName() {
    return 'PUPPETLINT';
  }

  public function getLinterConfigurationName() {
    return 'puppet-lint';
  }

  public function getDefaultBinary() {
    return 'puppet-lint';
  }

  public function getVersion() {
    list($stdout) = execx('%C --version', $this->getExecutableCommand());

    $matches = array();
    $regex = '/^Puppet-lint (?P<version>\d+\.\d+\.\d+)$/';
    if (preg_match($regex, $stdout, $matches)) {
      return $matches['version'];
    } else {
      return false;
    }
  }

  public function getInstallInstructions() {
    return pht('Install puppet-lint using `gem install puppet-lint`.');
  }

  public function shouldExpectCommandErrors() {
    return true;
  }

  public function supportsReadDataFromStdin() {
    return false;
  }

  protected function getMandatoryFlags() {
    return array(sprintf('--log-format=%s', implode('|', array(
      '%{linenumber}',
      '%{column}',
      '%{kind}',
      '%{check}',
      '%{message}',
      ))),
    );
  }

  public function getLinterConfigurationOptions() {
    $options = array(
      'puppet-lint.config' => array(
        'type' => 'optional string',
        'help' => pht('Pass in a custom configuration file path.'),
      ),
    );

    return $options + parent::getLinterConfigurationOptions();
  }

  public function setLinterConfigurationValue($key, $value) {
    switch ($key) {
      case 'puppet-lint.config':
        $this->config = $value;
        return;
    }

    return parent::setLinterConfigurationValue($key, $value);
  }

  protected function getDefaultFlags() {
    $options = array();

    if ($this->config) {
      $options[] = '--config='.$this->config;
    }

    return $options;
  }

  protected function parseLinterOutput($path, $err, $stdout, $stderr) {
    $lines = phutil_split_lines($stdout, false);

    $messages = array();
    foreach ($lines as $line) {
      $matches = explode('|', $line, 5);

      if (count($matches) === 5) {
        $message = new ArcanistLintMessage();
        $message->setPath($path);
        $message->setLine($matches[0]);
        $message->setChar($matches[1]);
        $message->setName(ucwords(str_replace('_', ' ', $matches[3])));
        $message->setDescription(ucfirst($matches[4]));

        switch ($matches[2]) {
          case 'warning':
            $message->setSeverity(ArcanistLintSeverity::SEVERITY_WARNING);
            break;

          case 'error':
            $message->setSeverity(ArcanistLintSeverity::SEVERITY_ERROR);
            break;

          default:
            $message->setSeverity(ArcanistLintSeverity::SEVERITY_ADVICE);
            break;
        }

        $messages[] = $message;
      }
    }

    if ($err && !$messages) {
      return false;
    }

    return $messages;
  }

}
