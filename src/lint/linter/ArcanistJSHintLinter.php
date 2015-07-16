<?php

/**
 * Uses JSHint to detect errors and potential problems in JavaScript code.
 */
final class ArcanistJSHintLinter extends ArcanistExternalLinter {

  private $jshintignore;
  private $jshintrc;

  public function getInfoName() {
    return 'JSHint';
  }

  public function getInfoURI() {
    return 'http://www.jshint.com';
  }

  public function getInfoDescription() {
    return pht(
      'Use `%s` to detect issues with JavaScript source files.',
      'jshint');
  }

  public function getLinterName() {
    return 'JSHint';
  }

  public function getLinterConfigurationName() {
    return 'jshint';
  }

  protected function getDefaultMessageSeverity($code) {
    if (preg_match('/^W/', $code)) {
      return ArcanistLintSeverity::SEVERITY_WARNING;
    } else if (preg_match('/^E043$/', $code)) {
      // TODO: If JSHint encounters a large number of errors, it will quit
      // prematurely and add an additional "Too Many Errors" error. Ideally, we
      // should be able to pass some sort of `--force` option to `jshint`.
      //
      // See https://github.com/jshint/jshint/issues/180
      return ArcanistLintSeverity::SEVERITY_DISABLED;
    } else {
      return ArcanistLintSeverity::SEVERITY_ERROR;
    }
  }

  public function getDefaultBinary() {
    return 'jshint';
  }

  public function getVersion() {
    // NOTE: `jshint --version` emits version information on stderr, not stdout.
    list($stdout, $stderr) = execx(
      '%C --version',
      $this->getExecutableCommand());

    $matches = array();
    $regex = '/^jshint v(?P<version>\d+\.\d+\.\d+)$/';
    if (preg_match($regex, $stderr, $matches)) {
      return $matches['version'];
    } else {
      return false;
    }
  }

  public function getInstallInstructions() {
    return pht('Install JSHint using `%s`.', 'npm install -g jshint');
  }

  protected function getMandatoryFlags() {
    $options = array();

    $options[] = '--reporter='.dirname(realpath(__FILE__)).'/reporter.js';

    if ($this->jshintrc) {
      $options[] = '--config='.$this->jshintrc;
    }

    if ($this->jshintignore) {
      $options[] = '--exclude-path='.$this->jshintignore;
    }

    return $options;
  }

  public function getLinterConfigurationOptions() {
    $options = array(
      'jshint.jshintignore' => array(
        'type' => 'optional string',
        'help' => pht('Pass in a custom jshintignore file path.'),
      ),
      'jshint.jshintrc' => array(
        'type' => 'optional string',
        'help' => pht('Custom configuration file.'),
      ),
    );

    return $options + parent::getLinterConfigurationOptions();
  }

  public function setLinterConfigurationValue($key, $value) {
    switch ($key) {
      case 'jshint.jshintignore':
        $this->jshintignore = $value;
        return;

      case 'jshint.jshintrc':
        $this->jshintrc = $value;
        return;
    }

    return parent::setLinterConfigurationValue($key, $value);
  }

  protected function parseLinterOutput($path, $err, $stdout, $stderr) {
    $errors = null;
    try {
      $errors = phutil_json_decode($stdout);
    } catch (PhutilJSONParserException $ex) {
      // Something went wrong and we can't decode the output. Exit abnormally.
      throw new PhutilProxyException(
        pht('JSHint returned unparseable output.'),
        $ex);
    }

    $messages = array();
    foreach ($errors as $err) {
      $message = new ArcanistLintMessage();
      $message->setPath($path);
      $message->setLine(idx($err, 'line'));
      $message->setChar(idx($err, 'col'));
      $message->setCode(idx($err, 'code'));
      $message->setName('JSHint'.idx($err, 'code'));
      $message->setDescription(idx($err, 'reason'));
      $message->setSeverity($this->getLintMessageSeverity(idx($err, 'code')));

      $messages[] = $message;
    }

    return $messages;
  }

  protected function getLintCodeFromLinterConfigurationKey($code) {
    if (!preg_match('/^(E|W)\d+$/', $code)) {
      throw new Exception(
        pht(
          'Unrecognized lint message code "%s". Expected a valid JSHint '.
          'lint code like "%s" or "%s".',
          $code,
          'E033',
          'W093'));
    }

    return $code;
  }

}
