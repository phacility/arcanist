<?php

/**
 * A linter for Puppet files.
 */
final class UberPupetLintLinter extends ArcanistExternalLinter {

  private $config;

  public function getInfoName() {
    return 'uber-puppet-lint';
  }

  public function getInfoURI() {
    return 'http://t.uber.com/uber-puppet-lint';
  }

  public function getInfoDescription() {
    return pht(
      'Use `%s` to check that your Puppet manifests '.
      'conform to the style guide.',
      'puppet-lint');
  }

  public function getLinterName() {
    return 'UBERPUPPETLINT';
  }

  public function getLinterConfigurationName() {
    return 'uber-puppet-lint';
  }

  public function getDefaultBinary() {
    return $this->getProjectRoot().'/scripts/uber-puppet-lint';
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
      'Please visit %s for instructions',
      $this->getInstallInstructions());
  }

  protected function getMandatoryFlags() {
    return array(
      '--error-level=all',
      sprintf('--log-format=%s', implode('|', array(
        '%{linenumber}',
        '%{column}',
        '%{kind}',
        '%{check}',
        '%{message}',
      ))),
    );
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
