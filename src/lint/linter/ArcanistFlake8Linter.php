<?php

/**
 * Uses "flake8" to detect various errors in Python code.
 * Requires version 1.7.0 or newer of flake8.
 *
 * @group linter
 */
final class ArcanistFlake8Linter extends ArcanistLinter {

  public function willLintPaths(array $paths) {
    return;
  }

  public function getLinterName() {
    return 'flake8';
  }

  public function getFlake8Options() {
    $working_copy = $this->getEngine()->getWorkingCopy();

    $options = $working_copy->getConfig('lint.flake8.options', '');

    return $options;
  }

  public function getFlake8Path() {
    $working_copy = $this->getEngine()->getWorkingCopy();
    $prefix = $working_copy->getConfig('lint.flake8.prefix');
    $bin = $working_copy->getConfig('lint.flake8.bin');

    if ($bin === null && $prefix === null) {
      $bin = 'flake8';
    } else {
      if ($bin === null) {
        $bin = 'flake8';
      }

      if ($prefix !== null) {
        if (!Filesystem::pathExists($prefix.'/'.$bin)) {
          throw new ArcanistUsageException(
            "Unable to find flake8 binary in a specified directory. Make sure ".
            "that 'lint.flake8.prefix' and 'lint.flake8.bin' keys are set ".
            "correctly. If you'd rather use a copy of flake8 installed ".
            "globally, you can just remove these keys from your .arcconfig");
        }

        $bin = csprintf("%s/%s", $prefix, $bin);

        return $bin;
      }

      // Look for globally installed flake8
      list($err) = exec_manual('which %s', $bin);
      if ($err) {
        throw new ArcanistUsageException(
          "flake8 does not appear to be installed on this system. Install it ".
          "(e.g., with 'easy_install flake8') or configure ".
          "'lint.flake8.prefix' in your .arcconfig to point to the directory ".
          "where it resides.");
      }
    }

    return $bin;
  }

  public function lintPath($path) {
    $flake8_bin = $this->getFlake8Path();
    $options = $this->getFlake8Options();

    $f = new ExecFuture("%C %C -", $flake8_bin, $options);
    $f->write($this->getData($path));

    list($err, $stdout, $stderr) = $f->resolve();

    if ($err === 2) {
      throw new Exception("flake8 failed to run correctly:\n".$stderr);
    }

    $lines = explode("\n", $stdout);
    $messages = array();
    foreach ($lines as $line) {
      $matches = null;
      // stdin:2: W802 undefined name 'foo'  # pyflakes
      // stdin:3:1: E302 expected 2 blank lines, found 1  # pep8
      if (!preg_match('/^(.*?):(\d+):(?:(\d+):)? (\S+) (.*)$/',
                      $line, $matches)) {
        continue;
      }
      foreach ($matches as $key => $match) {
        $matches[$key] = trim($match);
      }

      $severity = ArcanistLintSeverity::SEVERITY_WARNING;
      $description = $matches[3];

      $error_regexp = '/(^undefined|^duplicate|before assignment$)/';
      if (preg_match($error_regexp, $description)) {
        $severity = ArcanistLintSeverity::SEVERITY_ERROR;
      }

      $message = new ArcanistLintMessage();
      $message->setPath($path);
      $message->setLine($matches[2]);
      if (!empty($matches[3]))
        $message->setChar($matches[3]);
      $message->setCode($matches[4]);
      $message->setName($this->getLinterName().' '.$matches[4]);
      $message->setDescription($description);
      $message->setSeverity($severity);
      $this->addLintMessage($message);
    }
  }

}
