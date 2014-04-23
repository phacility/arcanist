<?php

/**
 * Uses "pep8.py" to enforce PEP8 rules for Python.
 *
 * @group linter
 */
final class ArcanistPEP8Linter extends ArcanistExternalLinter {

  public function getLinterName() {
    return 'PEP8';
  }

  public function getLinterConfigurationName() {
    return 'pep8';
  }

  public function getCacheVersion() {
    list($stdout) = execx('%C --version', $this->getExecutableCommand());
    return $stdout.implode(' ', $this->getCommandFlags());
  }

  public function getDefaultFlags() {
    // TODO: Warn that all of this is deprecated.
    $config = $this->getEngine()->getConfigurationManager();
    return $config->getConfigFromAnySource(
      'lint.pep8.options',
      $this->getConfig('options', array()));
  }

  public function shouldUseInterpreter() {
    return ($this->getDefaultBinary() !== 'pep8');
  }

  public function getDefaultInterpreter() {
    return 'python2.6';
  }

  public function getDefaultBinary() {
    if (Filesystem::binaryExists('pep8')) {
      return 'pep8';
    }

    $config = $this->getEngine()->getConfigurationManager();
    $old_prefix = $config->getConfigFromAnySource('lint.pep8.prefix');
    $old_bin = $config->getConfigFromAnySource('lint.pep8.bin');

    if ($old_prefix || $old_bin) {
      // TODO: Deprecation warning.
      $old_bin = nonempty($old_bin, 'pep8');
      return $old_prefix.'/'.$old_bin;
    }

    $arc_root = dirname(phutil_get_library_root('arcanist'));
    return $arc_root.'/externals/pep8/pep8.py';
  }

  public function getInstallInstructions() {
    return pht('Install PEP8 using `easy_install pep8`.');
  }

  public function shouldExpectCommandErrors() {
    return true;
  }

  protected function parseLinterOutput($path, $err, $stdout, $stderr) {
    $lines = phutil_split_lines($stdout, $retain_endings = false);

    $messages = array();
    foreach ($lines as $line) {
      $matches = null;
      if (!preg_match('/^(.*?):(\d+):(\d+): (\S+) (.*)$/', $line, $matches)) {
        continue;
      }
      foreach ($matches as $key => $match) {
        $matches[$key] = trim($match);
      }
      $message = new ArcanistLintMessage();
      $message->setPath($path);
      $message->setLine($matches[2]);
      $message->setChar($matches[3]);
      $message->setCode($matches[4]);
      $message->setName('PEP8 '.$matches[4]);
      $message->setDescription($matches[5]);
      $message->setSeverity($this->getLintMessageSeverity($matches[4]));

      $messages[] = $message;
    }

    if ($err && !$messages) {
      return false;
    }

    return $messages;
  }

  protected function getDefaultMessageSeverity($code) {
    if (preg_match('/^W/', $code)) {
      return ArcanistLintSeverity::SEVERITY_WARNING;
    } else {

      // TODO: Once severities/.arclint are more usable, restore this to
      // "ERROR".
      // return ArcanistLintSeverity::SEVERITY_ERROR;

      return ArcanistLintSeverity::SEVERITY_WARNING;
    }
  }

  protected function getLintCodeFromLinterConfigurationKey($code) {
    if (!preg_match('/^(E|W)\d+$/', $code)) {
      throw new Exception(
        pht(
          'Unrecognized lint message code "%s". Expected a valid PEP8 '.
          'lint code like "%s" or "%s".',
          $code,
          "E101",
          "W291"));
    }

    return $code;
  }

}
