<?php

/**
 * Uses "PHP_CodeSniffer" to detect checkstyle errors in php code.
 * To use this linter, you must install PHP_CodeSniffer.
 * http://pear.php.net/package/PHP_CodeSniffer.
 *
 * Optional configurations in .arcconfig:
 *
 *   lint.phpcs.standard
 *   lint.phpcs.options
 *   lint.phpcs.bin
 *
 * @group linter
 */
final class ArcanistPhpcsLinter extends ArcanistExternalLinter {

  private $reports;

  private $phpcsBin;
  private $phpcsOptions;
  private $phpcsStandard;
  private $phpcsStdin = true;

  public function setPhpcsBin($value) {
    $this->phpcsBin = $value;
  }

  public function setPhpcsOptions($value) {
    $this->phpcsOptions = $value;
  }

  public function setPhpcsStandard($value) {
    $this->phpcsStandard = $value;
  }

  public function setPhpcsStdin($value) {
    $this->phpcsStdin = $value;
  }

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

  public function getMandatoryFlags() {
    return array('--report=xml');
  }

  public function getInstallInstructions() {
    return pht('Install PHPCS with `pear install PHP_CodeSniffer`.');
  }

  public function getDefaultFlags() {
    $options = $this->getDefaultOptions();
    $standard = $this->phpcsStandard;

    if (!empty($standard)) {
      if (is_array($options)) {
        $options[] = '--standard='.$standard;
      } else {
        $options .= ' --standard='.$standard;
      }
    }

    return $options;
  }

  public function getDefaultBinary() {
    return !empty($this->phpcsBin) ? $this->phpcsBin : 'phpcs';
  }

  public function getDefaultOptions() {
    return !empty($this->phpcsOptions) ? $this->phpcsOptions : array();
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

  public function shouldExpectCommandErrors() {
    return true;
  }

  public function supportsReadDataFromStdin() {
    return $this->phpcsStdin;
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
        "Invalid severity code '{$code}', should begin with 'PHPCS.'.");
    }
    return $code;
  }

  public function getLinterConfigurationOptions() {
    $options = array(
      'phpcs.bin' => array(
        'type' => 'optional string',
        'help' => pht(
          'Path to PHP CodeSniffer binary.'
        ),
      ),
      'phpcs.options' => array(
        'type' => 'optional string',
        'help' => pht(
          'Extra options to use in PHP CodeSniffer.'
        ),
      ),
      'phpcs.standard' => array(
        'type' => 'string',
        'help' => pht(
          'Coding standard to use in PHP CodeSniffer.'
        ),
      ),
      'phpcs.stdin' => array(
        'type' => 'optional bool',
        'help' => pht(
          'Use PHP CodeSniffer STDIN.'
        ),
      )
    );

    return $options + parent::getLinterConfigurationOptions();
  }

  public function setLinterConfigurationValue($key, $value) {
    switch ($key) {
      case 'phpcs.bin':
        $this->setPhpcsBin($value);
        return;
      case 'phpcs.options':
        $this->setPhpcsOptions($value);
        return;
      case 'phpcs.standard':
        $this->setPhpcsStandard($value);
        return;
      case 'phpcs.stdin':
        $this->setPhpcsStdin($value);
        return;
    }

    return parent::setLinterConfigurationValue($key, $value);
  }
}
