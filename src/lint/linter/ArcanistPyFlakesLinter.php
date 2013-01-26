<?php

/**
 * Uses "PyFlakes" to detect various errors in Python code.
 *
 * @group linter
 */
final class ArcanistPyFlakesLinter extends ArcanistLinter {

  public function willLintPaths(array $paths) {
    return;
  }

  public function getLinterName() {
    return 'PyFlakes';
  }

  public function getLintSeverityMap() {
    return array();
  }

  public function getLintNameMap() {
    return array(
    );
  }

  public function getPyFlakesOptions() {
    return null;
  }

  public function lintPath($path) {
    $working_copy = $this->getEngine()->getWorkingCopy();
    $pyflakes_path = $working_copy->getConfig('lint.pyflakes.path');
    $pyflakes_prefix = $working_copy->getConfig('lint.pyflakes.prefix');

    // Default to just finding pyflakes in the users path
    $pyflakes_bin = 'pyflakes';
    $python_path = array();

    // If a pyflakes path was specified, then just use that as the
    // pyflakes binary and assume that the libraries will be imported
    // correctly.
    //
    // If no pyflakes path was specified and a pyflakes prefix was
    // specified, then use the binary from this prefix and add it to
    // the PYTHONPATH environment variable so that the libs are imported
    // correctly.  This is useful when pyflakes is installed into a
    // non-default location.
    if ($pyflakes_path !== null) {
      $pyflakes_bin = $pyflakes_path;
    } else if ($pyflakes_prefix !== null) {
      $pyflakes_bin = $pyflakes_prefix.'/bin/pyflakes';
      $python_path[] = $pyflakes_prefix.'/lib/python2.7/site-packages';
      $python_path[] = $pyflakes_prefix.'/lib/python2.7/dist-packages';
      $python_path[] = $pyflakes_prefix.'/lib/python2.6/site-packages';
      $python_path[] = $pyflakes_prefix.'/lib/python2.6/dist-packages';
    }
    $python_path[] = '';
    $python_path = implode(':', $python_path);
    $options = $this->getPyFlakesOptions();

    $f = new ExecFuture(
          "/usr/bin/env PYTHONPATH=%s\$PYTHONPATH ".
            "{$pyflakes_bin} {$options}", $python_path);
    $f->write($this->getData($path));

    try {
      list($stdout, $_) = $f->resolvex();
    } catch (CommandException $e) {
      // PyFlakes will return an exit code of 1 if warnings/errors
      // are found but print nothing to stderr in this case.  Therefore,
      // if we see any output on stderr or a return code other than 1 or 0,
      // pyflakes failed.
      if ($e->getError() !== 1 || $e->getStderr() !== '') {
        throw $e;
      } else {
        $stdout = $e->getStdout();
      }
    }

    $lines = explode("\n", $stdout);
    $messages = array();
    foreach ($lines as $line) {
      $matches = null;
      if (!preg_match('/^(.*?):(\d+): (.*)$/', $line, $matches)) {
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
      $message->setCode($this->getLinterName());
      $message->setDescription($description);
      $message->setSeverity($severity);
      $this->addLintMessage($message);
    }
  }

}
