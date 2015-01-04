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
    return pht('Install CoffeeLint using `npm install -g coffeelint`.');
  }

  public function shouldExpectCommandErrors() {
    return true;
  }

  public function supportsReadDataFromStdin() {
    return true;
  }

  public function getReadDataFromStdinFilename() {
    return '--stdin';
  }

  protected function getMandatoryFlags() {
    $options = array(
      '--reporter=checkstyle',
      '--color=never',
      '--quiet',
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
    $report_dom = new DOMDocument();
    $ok = @$report_dom->loadXML($stdout);

    if (!$ok) {
      return false;
    }

    $files = $report_dom->getElementsByTagName('file');
    $messages = array();
    foreach ($files as $file) {
      foreach ($file->getElementsByTagName('error') as $error) {

        // Column number is not provided in the output.
        // See https://github.com/clutchski/coffeelint/issues/87

        $message = new ArcanistLintMessage();
        $message->setPath($path);
        $message->setLine($error->getAttribute('line'));
        $message->setCode($this->getLinterName());
        $message->setDescription(preg_replace(
          '/; context: .*$/',
          '.',
          $error->getAttribute('message')));

        switch ($error->getAttribute('severity')) {
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
