<?php

final class ArcanistJscsLinter extends ArcanistExternalLinter {

  private $config;
  private $preset;

  public function getInfoName() {
    return 'JavaScript Code Style';
  }

  public function getInfoURI() {
    return 'https://github.com/mdevils/node-jscs';
  }

  public function getInfoDescription() {
    return pht(
      'Use `%s` to detect issues with Javascript source files.',
      'jscs');
  }

  public function getLinterName() {
    return 'JSCS';
  }

  public function getLinterConfigurationName() {
    return 'jscs';
  }

  public function getDefaultBinary() {
    return 'jscs';
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
    return pht('Install JSCS using `%s`.', 'npm install -g jscs');
  }

  protected function getMandatoryFlags() {
    $options = array();

    $options[] = '--reporter=checkstyle';
    $options[] = '--no-colors';

    if ($this->config) {
      $options[] = '--config='.$this->config;
    }

    if ($this->preset) {
      $options[] = '--preset='.$this->preset;
    }

    return $options;
  }

  public function getLinterConfigurationOptions() {
    $options = array(
      'jscs.config' => array(
        'type' => 'optional string',
        'help' => pht('Pass in a custom %s file path.', 'jscsrc'),
      ),
      'jscs.preset' => array(
        'type' => 'optional string',
        'help' => pht('Custom preset.'),
      ),
    );

    return $options + parent::getLinterConfigurationOptions();
  }

  public function setLinterConfigurationValue($key, $value) {
    switch ($key) {
      case 'jscs.config':
        $this->config = $value;
        return;

      case 'jscs.preset':
        $this->preset = $value;
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

    $messages = array();
    foreach ($report_dom->getElementsByTagName('file') as $file) {
      foreach ($file->getElementsByTagName('error') as $error) {
        $message = new ArcanistLintMessage();
        $message->setPath($path);
        $message->setLine($error->getAttribute('line'));
        $message->setChar($error->getAttribute('column'));
        $message->setCode('JSCS');
        $message->setName('JSCS');
        $message->setDescription($error->getAttribute('message'));

        switch ($error->getAttribute('severity')) {
          case 'error':
            $message->setSeverity(ArcanistLintSeverity::SEVERITY_ERROR);
            break;

          case 'warning':
            $message->setSeverity(ArcanistLintSeverity::SEVERITY_WARNING);
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
    //
    // See https://github.com/mdevils/node-jscs/issues/224

    throw new Exception(
      pht(
        "JSCS does not currently support custom severity levels, because ".
        "rules can't be identified from messages in output."));
  }

}
