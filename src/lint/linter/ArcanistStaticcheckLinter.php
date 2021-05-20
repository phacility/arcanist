<?php

/**
 * Uses Staticcheck (a golint successsor) to lint Go code.
 * This linter was tested against Staticcheck 2020.2.1.
 */
final class ArcanistStaticcheckLinter extends ArcanistExternalLinter
{

  public function getInfoName()
  {
    return 'Staticcheck';
  }

  public function getInfoURI()
  {
    return 'https://staticcheck.io/';
  }

  public function getInfoDescription()
  {
    return pht(
      'Staticcheck is a state of the art linter for the Go programming ' .
      'language. Using static analysis, it finds bugs and performance ' .
      'issues, offers simplifications, and enforces style rules.');
  }

  public function getLinterName()
  {
    return 'Staticcheck';
  }

  public function getLinterConfigurationName()
  {
    return 'staticcheck';
  }

  public function getDefaultBinary()
  {
    return 'staticcheck';
  }

  public function getVersion()
  {
    // Returns a string like: `staticcheck 2020.2.4 (v0.1.4)`
    list($stdout) = execx('%C -version', $this->getExecutableCommand());
    return $stdout;
  }

  public function getInstallInstructions()
  {
    return pht(
      'Install Staticcheck using `%s`.',
      'go install honnef.co/go/tools/cmd/staticcheck@latest'
    );
  }

  /**
   * Prepare the path to be added to the command string.
   *
   * This method is expected to return an already escaped string.
   *
   * @param string Path to the file being linted
   * @return string The command-ready file argument
   */
  protected function getPathArgumentForLinterFuture($path)
  {
    return dirname(csprintf('%s', $path));
  }

  protected function parseLinterOutput($path, $err, $stdout, $stderr)
  {
    $lines = phutil_split_lines($stdout, false);

    $messages = array();
    // $line looks like:
    //    path/to/file.go:19:5: var problem is unused (U1000)
    foreach ($lines as $line) {
      $matches = null;
      if (!preg_match('/^(.*?):(\d+):(\d+): (.+) \((.*)\)$/', $line, $matches)) {
        continue;
      }
      // https://github.com/dominikh/go-tools/issues/232
      // staticcheck runs per-module, not per-file, so skip files in the module
      // that were scanned, but don't match $path
      if ($matches[1] != $path) {
        continue;
      }
      foreach ($matches as $key => $match) {
        $matches[$key] = trim($match);
      }
      $message = new ArcanistLintMessage();
      $message->setPath($path);
      $message->setLine($matches[2]);
      $message->setChar($matches[3]);
      $message->setDescription($matches[4]);
      $message->setCode($matches[5]);
      $message->setSeverity($this->getLintMessageSeverity($matches[5]));
      $message->setName(
        'Staticcheck check https://staticcheck.io/docs/checks#' . $matches[5]
      );

      $messages[] = $message;
    }

    return $messages;
  }

  /**
   * Default to "error" severity. See "Adjusting Message Severities" on
   * https://secure.phabricator.com/book/phabricator/article/arcanist_lint/#configuring-lint
   * for more.
   *
   * @param string  Code specified in configuration.
   * @return string  Normalized code to use in severity map.
   */
  protected function getDefaultMessageSeverity($code)
  {
    return ArcanistLintSeverity::SEVERITY_ERROR;
  }

  /**
   * Raise exception on unrecognized codes.
   *
   * Formula! S (or U), optionally followed by a letter, then four digits.
   *
   * @param string  Code specified in configuration.
   */
  protected function getLintCodeFromLinterConfigurationKey($code)
  {
    // Beware that U1000 (variable unused) and U1001 aren't listed on their
    // website: https://github.com/dominikh/go-tools/issues/740
    if (!preg_match('/^[SU][A-Z]?\d{4}$/', $code)) {
      throw new Exception(
        pht(
          'Unrecognized lint message code "%s". Expected a valid Staticcheck ' .
          'check code like "%s" or "%s": https://staticcheck.io/docs/checks',
          $code,
          'S1001',
          'S1039')
      );
    }

    return $code;
  }
}
