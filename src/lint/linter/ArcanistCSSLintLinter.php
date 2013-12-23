<?php

/**
 * Uses "CSS lint" to detect checkstyle errors in css code.
 * To use this linter, you must install CSS lint.
 * ##npm install csslint -g## (don't forget the -g flag or NPM will install
 * the package locally).
 *
 * Based on ArcanistPhpcsLinter.php
 *
 *   lint.csslint.options
 *   lint.csslint.bin
 *
 * @group linter
 */
final class ArcanistCSSLintLinter extends ArcanistExternalLinter {

  public function getLinterName() {
    return 'CSSLint';
  }

  public function getLinterConfigurationName() {
    return 'csslint';
  }

  public function getMandatoryFlags() {
    return '--format=lint-xml';
  }

  public function getDefaultFlags() {
    $config = $this->getEngine()->getConfigurationManager();

    $options = $config->getConfigFromAnySource('lint.csslint.options');
    // TODO: Deprecation warning.

    return $options;
  }

  public function getDefaultBinary() {
    // TODO: Deprecation warning.
    $config = $this->getEngine()->getConfigurationManager();
    $bin = $config->getConfigFromAnySource('lint.csslint.bin');
    if ($bin) {
      return $bin;
    }

    return 'csslint';
  }

  public function getInstallInstructions() {
    return pht('Install CSSLint using `npm install -g csslint`.');
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
        if (!($child instanceof DOMElement)) {
          continue;
        }

        $data = $this->getData($path);
        $lines = explode("\n", $data);
        $name = $child->getAttribute('reason');
        $severity = ($child->getAttribute('severity') == 'warning')
          ? ArcanistLintSeverity::SEVERITY_WARNING
          : ArcanistLintSeverity::SEVERITY_ERROR;

        $message = new ArcanistLintMessage();
        $message->setPath($path);
        $message->setLine($child->getAttribute('line'));
        $message->setChar($child->getAttribute('char'));
        $message->setCode('CSSLint');
        $message->setDescription($child->getAttribute('reason'));
        $message->setSeverity($severity);

        if ($child->hasAttribute('line')) {
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
        "CSSLint does not currently support custom severity levels, because ".
        "rules can't be identified from messages in output. ".
        "See Pull Request #409."));
  }

}
