<?php

/**
 * Uses JSHint to detect errors and potential problems in JavaScript code.
 *
 * @group linter
 */
final class ArcanistJSHintLinter extends ArcanistExternalLinter {

  public function getLinterName() {
    return 'JSHint';
  }

  public function getLinterConfigurationName() {
    return 'jshint';
  }

  protected function getDefaultMessageSeverity($code) {
    if (preg_match('/^W/', $code)) {
      return ArcanistLintSeverity::SEVERITY_WARNING;
    } else {
      return ArcanistLintSeverity::SEVERITY_ERROR;
    }
  }

  public function getDefaultBinary() {
    $config = $this->getEngine()->getConfigurationManager();
    $prefix = $config->getConfigFromAnySource('lint.jshint.prefix');
    $bin = $config->getConfigFromAnySource('lint.jshint.bin', 'jshint');

    if ($prefix) {
      return $prefix.'/'.$bin;
    } else {
      return $bin;
    }
  }

  public function getCacheVersion() {
    list($stdout) = execx('%C --version', $this->getExecutableCommand());

    // Extract version number from standard output.
    $matches = array();
    if (preg_match('/^jshint v(\d+\.\d+\.\d+)$/', $stdout, $matches)) {
      $version = $matches[1];
    } else {
      $version = md5($stdout);
    }

    return $version . '-' . md5(json_encode($this->getCommandFlags()));
  }

  public function getInstallInstructions() {
    return pht('Install JSHint using `npm install -g jshint`.');
  }

  public function shouldExpectCommandErrors() {
    return true;
  }

  public function supportsReadDataFromStdin() {
    return true;
  }

  public function getReadDataFromStdinFilename() {
    return '-';
  }

  public function getMandatoryFlags() {
    return array(
      '--reporter='.dirname(realpath(__FILE__)).'/reporter.js',
    );
  }

  public function getDefaultFlags() {
    $config_manager = $this->getEngine()->getConfigurationManager();
    $options = $config_manager->getConfigFromAnySource(
      'lint.jshint.options',
      array());

    $config = $config_manager->getConfigFromAnySource('lint.jshint.config');
    if ($config) {
      $options[] = '--config='.$config;
    }

    return $options;
  }

  protected function parseLinterOutput($path, $err, $stdout, $stderr) {
    $errors = json_decode($stdout);

    if (!is_array($errors)) {
      // Something went wrong and we can't decode the output. Exit abnormally.
      throw new ArcanistUsageException(
        "JSHint returned unparseable output.\n".
        "stdout:\n\n{$stdout}".
        "stderr:\n\n{$stderr}");
    }

    $messages = array();
    foreach ($errors as $err) {
      $message = new ArcanistLintMessage();
      $message->setPath($path);
      $message->setLine($err->line);
      $message->setChar($err->col);
      $message->setCode($err->code);
      $message->setName('JSHint'.$err->code);
      $message->setDescription($err->reason);
      $message->setSeverity($this->getLintMessageSeverity($err->code));
      $message->setOriginalText($err->evidence);

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
