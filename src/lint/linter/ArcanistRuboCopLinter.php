<?php

final class ArcanistRuboCopLinter extends ArcanistExternalLinter {

  private $config;

  public function getInfoName() {
    return 'Ruby static code analyzer';
  }

  public function getInfoURI() {
    return 'http://batsov.com/rubocop';
  }

  public function getInfoDescription() {
    return pht(
      'RuboCop is a Ruby static code analyzer, based on the community Ruby '.
      'style guide.');
  }

  public function getLinterName() {
    return 'RuboCop';
  }

  public function getLinterConfigurationName() {
    return 'rubocop';
  }

  public function getDefaultBinary() {
    return 'rubocop';
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
    return pht('Install RuboCop using `%s`.', 'gem install rubocop');
  }

  protected function getMandatoryFlags() {
    $options = array(
      '--format=json',
    );

    if ($this->config) {
      $options[] = '--config='.$this->config;
    }

    return $options;
  }

  public function getLinterConfigurationOptions() {
    $options = array(
      'rubocop.config' => array(
        'type' => 'optional string',
        'help' => pht('A custom configuration file.'),
      ),
    );

    return $options + parent::getLinterConfigurationOptions();
  }

  public function setLinterConfigurationValue($key, $value) {
    switch ($key) {
      case 'rubocop.config':
        $this->config = $value;
        return;
    }

    return parent::setLinterConfigurationValue($key, $value);
  }

  protected function parseLinterOutput($path, $err, $stdout, $stderr) {
    $results = phutil_json_decode($stdout);
    $messages = array();

    foreach ($results['files'] as $file) {
      foreach ($file['offenses'] as $offense) {
        $message = id(new ArcanistLintMessage())
          ->setPath($file['path'])
          ->setDescription($offense['message'])
          ->setLine($offense['location']['line'])
          ->setChar($offense['location']['column'])
          ->setSeverity($this->getLintMessageSeverity($offense['severity']))
          ->setName($this->getLinterName())
          ->setCode($offense['cop_name']);
        $messages[] = $message;
      }
    }

    return $messages;
  }

  /**
   * Take the string from RuboCop's severity terminology and return an
   * @{class:ArcanistLintSeverity}.
   */
  protected function getDefaultMessageSeverity($code) {
    switch ($code) {
      case 'convention':
      case 'refactor':
      case 'warning':
        return ArcanistLintSeverity::SEVERITY_WARNING;
      case 'error':
      case 'fatal':
        return ArcanistLintSeverity::SEVERITY_ERROR;
      default:
        return ArcanistLintSeverity::SEVERITY_ADVICE;
    }
  }

}
