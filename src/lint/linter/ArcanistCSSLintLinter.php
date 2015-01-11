<?php

/**
 * Uses "CSS Lint" to detect checkstyle errors in CSS code.
 */
final class ArcanistCSSLintLinter extends ArcanistExternalLinter {

  public function getInfoName() {
    return 'CSSLint';
  }

  public function getInfoURI() {
    return 'http://csslint.net';
  }

  public function getInfoDescription() {
    return pht(
      'Use `%s` to detect issues with CSS source files.',
      'csslint');
  }

  public function getLinterName() {
    return 'CSSLint';
  }

  public function getLinterConfigurationName() {
    return 'csslint';
  }

  protected function getMandatoryFlags() {
    return array(
      '--format=lint-xml',
    );
  }

  protected function getDefaultFlags() {
    return $this->getDeprecatedConfiguration('lint.csslint.options', array());
  }

  public function getDefaultBinary() {
    return $this->getDeprecatedConfiguration('lint.csslint.bin', 'csslint');
  }

  public function getVersion() {
    list($stdout) = execx('%C --version', $this->getExecutableCommand());

    $matches = array();
    if (preg_match('/^v(?P<version>\d+\.\d+\.\d+)$/', $stdout, $matches)) {
      return $matches['version'];
    } else {
      return false;
    }
  }

  public function getInstallInstructions() {
    return pht(
      'Install %s using `%s`.', 'CSSLint',
      'npm install -g csslint');
  }

  public function shouldExpectCommandErrors() {
    return true;
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
      foreach ($file->childNodes as $child) {
        $data = $this->getData($path);
        $lines = explode("\n", $data);
        $name = $child->getAttribute('reason');
        $severity = ($child->getAttribute('severity') == 'warning')
          ? ArcanistLintSeverity::SEVERITY_WARNING
          : ArcanistLintSeverity::SEVERITY_ERROR;

        $message = id(new ArcanistLintMessage())
          ->setPath($path)
          ->setLine($child->getAttribute('line'))
          ->setChar($child->getAttribute('char'))
          ->setCode('CSSLint')
          ->setSeverity($severity)
          ->setDescription($child->getAttribute('reason'));

        if ($child->hasAttribute('line') && $child->getAttribute('line') > 0) {
          $line = $lines[$child->getAttribute('line') - 1];
          $text = substr($line, $child->getAttribute('char') - 1);
          $message->setOriginalText($text);
        }

        $messages[] = $message;
      }
    }

    return $messages;
  }

  protected function getLintCodeFromLinterConfigurationKey($code) {
    // NOTE: We can't figure out which rule generated each message, so we
    // can not customize severities. I opened a pull request to add this
    // ability; see:
    //
    // https://github.com/stubbornella/csslint/pull/409
    throw new Exception(
      pht(
        "%s does not currently support custom severity levels, because ".
        "rules can't be identified from messages in output.",
        'CSSLint'));
  }

}
