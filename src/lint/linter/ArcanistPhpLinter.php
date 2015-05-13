<?php

/**
 * Uses "php -l" to detect syntax errors in PHP code.
 */
final class ArcanistPhpLinter extends ArcanistExternalLinter {

  const LINT_PARSE_ERROR  = 1;
  const LINT_FATAL_ERROR  = 2;

  public function getInfoName() {
    return 'php -l';
  }

  public function getInfoURI() {
    return 'http://php.net/';
  }

  public function getInfoDescription() {
    return pht('Checks for syntax errors in PHP files.');
  }

  public function getLinterName() {
    return 'PHP';
  }

  public function getLinterConfigurationName() {
    return 'php';
  }

  public function getLintNameMap() {
    return array(
      self::LINT_PARSE_ERROR  => pht('Parse Error'),
      self::LINT_FATAL_ERROR  => pht('Fatal Error'),
    );
  }

  protected function getMandatoryFlags() {
    return array('-l');
  }

  public function getInstallInstructions() {
    return pht('Install PHP.');
  }

  public function getDefaultBinary() {
    return 'php';
  }

  public function getVersion() {
    list($stdout) = execx(
      '%C --run %s',
      $this->getExecutableCommand(),
      'echo phpversion();');
    return $stdout;
  }

  protected function canCustomizeLintSeverities() {
    return false;
  }

  protected function parseLinterOutput($path, $err, $stdout, $stderr) {
    // Older versions of PHP had both on stdout, newer ones split it.
    // Combine stdout and stderr for consistency.
    $stdout = $stderr."\n".$stdout;
    $matches = array();

    $regex = '/^(PHP )?(?<type>.+) error: +(?<error>.+) in (?<file>.+) '.
      'on line (?<line>\d+)$/m';
    if (preg_match($regex, $stdout, $matches)) {
      $code = $this->getLintCodeFromLinterConfigurationKey($matches['type']);

      $message = id(new ArcanistLintMessage())
        ->setPath($path)
        ->setLine($matches['line'])
        ->setCode($this->getLinterName().$code)
        ->setName($this->getLintMessageName($code))
        ->setSeverity(ArcanistLintSeverity::SEVERITY_ERROR)
        ->setDescription($matches['error']);

      // `php -l` only returns the first error.
      return array($message);
    }

    return array();
  }

  protected function getLintCodeFromLinterConfigurationKey($code) {
    switch (phutil_utf8_strtolower($code)) {
      case 'parse':
        return self::LINT_PARSE_ERROR;

      case 'fatal':
        return self::LINT_FATAL_ERROR;

      default:
        throw new Exception(pht('Unrecognized lint message code: "%s"', $code));
    }
  }

}
