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

  public function getDefaultBinary() {
    return 'csslint';
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
        $message = id(new ArcanistLintMessage())
          ->setPath($path)
          ->setLine($child->getAttribute('line'))
          ->setChar($child->getAttribute('char'))
          ->setCode($this->getLinterName())
          ->setDescription($child->getAttribute('reason'))
          ->setOriginalText(
            substr(
              $child->getAttribute('evidence'),
              $child->getAttribute('char') - 1));

        switch ($child->getAttribute('severity')) {
          case 'error':
            $message->setSeverity(ArcanistLintSeverity::SEVERITY_ERROR);
            break;

          case 'warning':
            $message->setSeverity(ArcanistLintSeverity::SEVERITY_WARNING);
            break;

          default:
            $message->setSeverity(ArcanistLintSeverity::SEVERITY_ERROR);
            break;
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
