<?php

/**
 * Uses Google's `cpplint.py` to check code.
 */
final class ArcanistCpplintLinter extends ArcanistExternalLinter {

  public function getLinterName() {
    return 'C++ Google\'s Styleguide';
  }

  public function getLinterConfigurationName() {
    return 'cpplint';
  }

  public function getDefaultBinary() {
    return 'cpplint.py';
  }

  public function getInstallInstructions() {
    return pht(
      'Install cpplint.py using `%s`, and place it in your path with the '.
      'appropriate permissions set.',
      'wget https://raw.github.com'.
      '/google/styleguide/gh-pages/cpplint/cpplint.py');
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
      $regex = '/(\d+):\s*(.*)\s*\[(.*)\] \[(\d+)\]$/';
      if (!preg_match($regex, $line, $matches)) {
        continue;
      }
      foreach ($matches as $key => $match) {
        $matches[$key] = trim($match);
      }

      $severity = $this->getLintMessageSeverity($matches[3]);

      $message = new ArcanistLintMessage();
      $message->setPath($path);
      $message->setCode($matches[3]);
      $message->setName($matches[3]);
      $message->setDescription($matches[2]);
      $message->setSeverity($severity);

      // NOTE: "cpplint" raises some messages which apply to the whole file,
      // like "no #ifndef guard found". It raises these messages on line 0.

      // Arcanist messages should have a "null" line, not a "0" line, if they
      // aren't bound to a particular line number.

      $line = (int)$matches[1];
      if ($line > 0) {
        $message->setLine($line);
      }

      $messages[] = $message;
    }

    return $messages;
  }

  protected function getLintCodeFromLinterConfigurationKey($code) {
    if (!preg_match('@^[a-z_]+/[a-z0-9_+]+$@', $code)) {
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
