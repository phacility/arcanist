<?php

/**
 * Uses "PHP_CodeSniffer" to detect checkstyle errors in PHP code.
 */
final class ArcanistPhpcsLinter extends ArcanistExternalLinter {

  private $standard;

  public function getInfoName() {
    return 'PHP_CodeSniffer';
  }

  public function getInfoURI() {
    return 'http://pear.php.net/package/PHP_CodeSniffer/';
  }

  public function getInfoDescription() {
    return pht(
      'PHP_CodeSniffer tokenizes PHP, JavaScript and CSS files and '.
      'detects violations of a defined set of coding standards.');
  }

  public function getLinterName() {
    return 'PHPCS';
  }

  public function getLinterConfigurationName() {
    return 'phpcs';
  }

  public function getInstallInstructions() {
    return pht('Install PHPCS with `%s`.', 'pear install PHP_CodeSniffer');
  }

  public function getLinterConfigurationOptions() {
    $options = array(
      'phpcs.standard' => array(
        'type' => 'optional string',
        'help' => pht('The name or path of the coding standard to use.'),
      ),
    );

    return $options + parent::getLinterConfigurationOptions();
  }

  public function setLinterConfigurationValue($key, $value) {
    switch ($key) {
      case 'phpcs.standard':
        $this->standard = $value;
        return;

      default:
        return parent::setLinterConfigurationValue($key, $value);
    }
  }

  protected function getMandatoryFlags() {
    $options = array('--report=xml');

    if ($this->standard) {
      $options[] = '--standard='.$this->standard;
    }

    return $options;
  }

  public function getDefaultBinary() {
    return 'phpcs';
  }

  public function getVersion() {
    list($stdout) = execx('%C --version', $this->getExecutableCommand());

    $matches = array();
    $regex = '/^PHP_CodeSniffer version (?P<version>\d+\.\d+\.\d+)\b/';
    if (preg_match($regex, $stdout, $matches)) {
      return $matches['version'];
    } else {
      return false;
    }
  }

  protected function parseLinterOutput($path, $err, $stdout, $stderr) {
    // NOTE: Some version of PHPCS after 1.4.6 stopped printing a valid, empty
    // XML document to stdout in the case of no errors. If PHPCS exits with
    // error 0, just ignore output.
    if (!$err) {
      return array();
    }

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

        if ($child->tagName == 'error') {
          $prefix = 'E';
        } else {
          $prefix = 'W';
        }

        $code = 'PHPCS.'.$prefix.'.'.$child->getAttribute('source');

        $message = new ArcanistLintMessage();
        $message->setPath($path);
        $message->setLine($child->getAttribute('line'));
        $message->setChar($child->getAttribute('column'));
        $message->setCode($code);
        $message->setDescription($child->nodeValue);
        $message->setSeverity($this->getLintMessageSeverity($code));

        $messages[] = $message;
      }
    }

    return $messages;
  }

  protected function getDefaultMessageSeverity($code) {
    if (preg_match('/^PHPCS\\.W\\./', $code)) {
      return ArcanistLintSeverity::SEVERITY_WARNING;
    } else {
      return ArcanistLintSeverity::SEVERITY_ERROR;
    }
  }

  protected function getLintCodeFromLinterConfigurationKey($code) {
    if (!preg_match('/^PHPCS\\.(E|W)\\./', $code)) {
      throw new Exception(
        pht(
          "Invalid severity code '%s', should begin with '%s.'.",
          $code,
          'PHPCS'));
    }
    return $code;
  }

}
