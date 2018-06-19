<?php

/**
 * A linter for Puppet files.
 */
final class ArcanistPuppetLintLinter extends ArcanistExternalLinter {

  private $config;

  public function getInfoName() {
    return 'puppet-lint';
  }

  public function getInfoURI() {
    return 'http://puppet-lint.com/';
  }

  public function getInfoDescription() {
    return pht(
      'Use `%s` to check that your Puppet manifests '.
      'conform to the style guide.',
      'puppet-lint');
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
    $regex = '/^puppet-lint (?P<version>\d+\.\d+\.\d+)$/';
    if (preg_match($regex, $stdout, $matches)) {
      return $matches['version'];
    } else {
      return false;
    }
  }

  public function getInstallInstructions() {
    return pht(
      'Install puppet-lint using `%s`.',
      'gem install puppet-lint');
  }

  protected function getMandatoryFlags() {
    return array(
      '--error-level=all',
      sprintf('--log-format=%s', implode('|', array(
        '%{line}',
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

      default:
        return parent::setLinterConfigurationValue($key, $value);
    }
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

      if (count($matches) < 5) {
        continue;
      }

      $message = id(new ArcanistLintMessage())
        ->setPath($path)
        ->setLine($matches[0])
        ->setChar($matches[1])
        ->setCode($this->getLinterName())
        ->setName(ucwords(str_replace('_', ' ', $matches[3])))
        ->setDescription(ucfirst($matches[4]));

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

    return $messages;
  }

}
