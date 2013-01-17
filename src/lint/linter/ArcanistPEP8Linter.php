<?php

/**
 * Uses "pep8.py" to enforce PEP8 rules for Python.
 *
 * @group linter
 */
final class ArcanistPEP8Linter extends ArcanistLinter {

  public function willLintPaths(array $paths) {
    return;
  }

  public function getLinterName() {
    return 'PEP8';
  }

  public function getLintSeverityMap() {
    return array();
  }

  public function getLintNameMap() {
    return array();
  }

  public function getCacheVersion() {
    list($stdout) = execx('%C --version', $this->getPEP8Path());
    return $stdout.$this->getPEP8Options();
  }

  public function getPEP8Options() {
    $working_copy = $this->getEngine()->getWorkingCopy();
    $options = $working_copy->getConfig('lint.pep8.options');

    if ($options === null) {
      $options = $this->getConfig('options');
    }

    return $options;
  }

  public function getPEP8Path() {
    $working_copy = $this->getEngine()->getWorkingCopy();
    $prefix = $working_copy->getConfig('lint.pep8.prefix');
    $bin = $working_copy->getConfig('lint.pep8.bin');

    if ($bin === null && $prefix === null) {
      $bin = csprintf('/usr/bin/env python2.6 %s',
               phutil_get_library_root('arcanist').
               '/../externals/pep8/pep8.py');
    } else {
      if ($bin === null) {
        $bin = 'pep8';
      }

      if ($prefix !== null) {
        if (!Filesystem::pathExists($prefix.'/'.$bin)) {
          throw new ArcanistUsageException(
            "Unable to find PEP8 binary in a specified directory. Make sure ".
            "that 'lint.pep8.prefix' and 'lint.pep8.bin' keys are set ".
            "correctly. If you'd rather use a copy of PEP8 installed ".
            "globally, you can just remove these keys from your .arcconfig.");
        }

        $bin = csprintf("%s/%s", $prefix, $bin);

        return $bin;
      }

      // Look for globally installed PEP8
      list($err) = exec_manual('which %s', $bin);
      if ($err) {
        throw new ArcanistUsageException(
          "PEP8 does not appear to be installed on this system. Install it ".
          "(e.g., with 'easy_install pep8') or configure ".
          "'lint.pep8.prefix' in your .arcconfig to point to the directory ".
          "where it resides.");
      }
    }

    return $bin;
  }

  public function lintPath($path) {
    $pep8_bin = $this->getPEP8Path();
    $options = $this->getPEP8Options();

    list($rc, $stdout) = exec_manual(
      "%C %C %s",
      $pep8_bin,
      $options,
      $this->getEngine()->getFilePathOnDisk($path));

    $lines = explode("\n", $stdout);
    $messages = array();
    foreach ($lines as $line) {
      $matches = null;
      if (!preg_match('/^(.*?):(\d+):(\d+): (\S+) (.*)$/', $line, $matches)) {
        continue;
      }
      foreach ($matches as $key => $match) {
        $matches[$key] = trim($match);
      }
      if (!$this->isMessageEnabled($matches[4])) {
        continue;
      }
      $message = new ArcanistLintMessage();
      $message->setPath($path);
      $message->setLine($matches[2]);
      $message->setChar($matches[3]);
      $message->setCode($matches[4]);
      $message->setName('PEP8 '.$matches[4]);
      $message->setDescription($matches[5]);
      $message->setSeverity(ArcanistLintSeverity::SEVERITY_WARNING);
      $this->addLintMessage($message);
    }
  }

}
