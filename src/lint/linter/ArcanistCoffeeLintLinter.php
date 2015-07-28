<?php

final class ArcanistCoffeeLintLinter extends ArcanistExternalLinter {

  private $config;

  public function getInfoName() {
    return 'CoffeeLint';
  }

  public function getInfoURI() {
    return 'http://www.coffeelint.org';
  }

  public function getInfoDescription() {
    return pht(
      'CoffeeLint is a style checker that helps keep CoffeeScript '.
      'code clean and consistent.');
  }

  public function getLinterName() {
    return 'COFFEE';
  }

  public function getLinterConfigurationName() {
    return 'coffeelint';
  }

  public function getDefaultBinary() {
    return 'coffeelint';
  }

  public function getVersion() {
    list($stdout) = execx('%C --version', $this->getExecutableCommand());

    $matches = array();
    if (preg_match('/^(?P<version>\d+\.\d+\.\d+)$/', $stdout, $matches)) {
      return $matches['version'];
    } else {
      return false;
    }
  }

  public function getInstallInstructions() {
    return pht(
      'Install CoffeeLint using `%s`.',
      'npm install -g coffeelint');
  }

  protected function getMandatoryFlags() {
    $options = array(
      '--reporter=raw',
    );

    if ($this->config) {
      $options[] = '--file='.$this->config;
    }

    return $options;
  }

  public function getLinterConfigurationOptions() {
    $options = array(
      'coffeelint.config' => array(
        'type' => 'optional string',
        'help' => pht('A custom configuration file.'),
      ),
    );

    return $options + parent::getLinterConfigurationOptions();
  }

  public function setLinterConfigurationValue($key, $value) {
    switch ($key) {
      case 'coffeelint.config':
        $this->config = $value;
        return;
    }

    return parent::setLinterConfigurationValue($key, $value);
  }

  protected function parseLinterOutput($path, $err, $stdout, $stderr) {
    $messages = array();
    $output = phutil_json_decode($stdout);

    // We are only linting a single file.
    if (count($output) != 1) {
      return false;
    }

    foreach ($output as $reports) {
      foreach ($reports as $report) {
        // Column number is not provided in the output.
        // See https://github.com/clutchski/coffeelint/issues/87

        $message = id(new ArcanistLintMessage())
          ->setPath($path)
          ->setLine($report['lineNumber'])
          ->setCode($this->getLinterName())
          ->setName(ucwords(str_replace('_', ' ', $report['name'])))
          ->setDescription($report['message'])
          ->setOriginalText(idx($report, 'line'));

        switch ($report['level']) {
          case 'warn':
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

    return $messages;
  }

  protected function getLintCodeFromLinterConfigurationKey($code) {
    // NOTE: We can't figure out which rule generated each message, so we
    // can not customize severities.
    throw new Exception(
      pht(
        "CoffeeLint does not currently support custom severity levels, ".
        "because rules can't be identified from messages in output."));
  }

}
