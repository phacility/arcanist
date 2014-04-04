<?php

/**
 * Uses cppcheck to do basic checks in a cpp file
 *
 * You can get it here:
 *   http://cppcheck.sourceforge.net/
 *
 * @group linter
 */
final class ArcanistCppcheckLinter extends ArcanistLinter {

  public function getLinterName() {
    return 'cppcheck';
  }

  public function getLintOptions() {
    $config = $this->getEngine()->getConfigurationManager();
    // You will for sure want some options.  The below default tends to be ok
    return $config->getConfigFromAnySource(
      'lint.cppcheck.options',
      '-j2 --inconclusive --enable=performance,style,portability,information');
  }

  public function getLintPath() {
    $config = $this->getEngine()->getConfigurationManager();
    $prefix = $config->getConfigFromAnySource('lint.cppcheck.prefix');
    $bin = $config->getConfigFromAnySource('lint.cppcheck.bin', 'cppcheck');

    if ($prefix !== null) {
      if (!Filesystem::pathExists($prefix.'/'.$bin)) {
        throw new ArcanistUsageException(
          "Unable to find cppcheck binary in a specified directory. Make ".
          "sure that 'lint.cppcheck.prefix' and 'lint.cppcheck.bin' keys are ".
          "set correctly. If you'd rather use a copy of cppcheck installed ".
          "globally, you can just remove these keys from your .arcconfig.");
      }

      return csprintf("%s/%s", $prefix, $bin);
    }

    // Look for globally installed cppcheck
    list($err) = exec_manual('which %s', $bin);
    if ($err) {
      throw new ArcanistUsageException(
        "cppcheck does not appear to be installed on this system. Install ".
        "it (from http://cppcheck.sourceforge.net/) or configure ".
        "'lint.cppcheck.prefix' in your .arcconfig to point to the ".
        "directory where it resides."
      );
    }

    return $bin;
  }

  public function lintPath($path) {
    $bin = $this->getLintPath();
    $options = $this->getLintOptions();

    list($rc, $stdout, $stderr) = exec_manual(
      "%C %C --inline-suppr --xml-version=2 -q %s",
      $bin,
      $options,
      $this->getEngine()->getFilePathOnDisk($path));

    if ($rc === 1) {
      throw new Exception("cppcheck failed to run correctly:\n".$stderr);
    }

    $dom = new DOMDocument();
    libxml_clear_errors();
    if ($dom->loadXML($stderr) === false || libxml_get_errors()) {
      throw new ArcanistUsageException('cppcheck Linter failed to load ' .
        'output. Something happened when running cppcheck. ' .
        "Output:\n$stderr" .
        "\nTry running lint with --trace flag to get more details.");
    }


    $errors = $dom->getElementsByTagName('error');
    foreach ($errors as $error) {
      $loc_node = $error->getElementsByTagName('location');
      if (!$loc_node) {
        continue;
      }
      $location = $loc_node->item(0);
      if (!$location) {
        continue;
      }
      $file = $location->getAttribute('file');
      if ($file != Filesystem::resolvePath($path)) {
        continue;
      }
      $line = $location->getAttribute('line');

      $id = $error->getAttribute('id');
      $severity = $error->getAttribute('severity');
      $msg = $error->getAttribute('msg');
      $inconclusive = $error->getAttribute('inconclusive');
      $verbose_msg = $error->getAttribute('verbose');

      $severity_code = ArcanistLintSeverity::SEVERITY_WARNING;
      if ($inconclusive) {
        $severity_code = ArcanistLintSeverity::SEVERITY_ADVICE;
      } else if (stripos($severity, 'error') !== false) {
        $severity_code = ArcanistLintSeverity::SEVERITY_ERROR;
      }

      $message = new ArcanistLintMessage();
      $message->setPath($path);
      $message->setLine($line);
      $message->setCode($severity);
      $message->setName($id);
      $message->setDescription($msg);
      $message->setSeverity($severity_code);

      $this->addLintMessage($message);
    }
  }

}
