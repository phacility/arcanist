<?php

/**
 * Uses Google's `cpplint.py` to check code.
 */
final class ArcanistCpplintLinter extends ArcanistExternalLinter {

  public function getLinterName() {
    return 'CPPLINT';
  }

  public function getLinterConfigurationName() {
    return 'cpplint';
  }

  public function getDefaultBinary() {
    return 'cpplint';
  }

  public function getInstallInstructions() {
    return pht(
      'Install cpplint.py using `%s`.',
      'wget http://google-styleguide.googlecode.com'.
      '/svn/trunk/cpplint/cpplint.py');
  }

  protected function getDefaultMessageSeverity($code) {
    return ArcanistLintSeverity::SEVERITY_WARNING;
  }

  protected function parseLinterOutput($path, $err, $stdout, $stderr) {
    $lines = explode("\n", $stderr);

    $messages = array();
    foreach ($lines as $line) {
      $line = trim($line);
      $matches = null;
      $regex = '/^-:(\d+):\s*(.*)\s*\[(.*)\] \[(\d+)\]$/';
      if (!preg_match($regex, $line, $matches)) {
        continue;
      }
      foreach ($matches as $key => $match) {
        $matches[$key] = trim($match);
      }

      $severity = $this->getLintMessageSeverity($matches[3]);

      $message = new ArcanistLintMessage();
      $message->setPath($path);
      $message->setLine($matches[1]);
      $message->setCode($matches[3]);
      $message->setName($matches[3]);
      $message->setDescription($matches[2]);
      $message->setSeverity($severity);

      $messages[] = $message;
    }

    return $messages;
  }

  protected function getLintCodeFromLinterConfigurationKey($code) {
    if (!preg_match('@^[a-z_]+/[a-z_]+$@', $code)) {
      throw new Exception(
        pht(
          'Unrecognized lint message code "%s". Expected a valid cpplint '.
          'lint code like "%s" or "%s".',
          $code,
          'build/include_order',
          'whitespace/braces'));
    }

    return $code;
  }

}
