<?php

/**
 * Uses `sbt compile` to detect various warnings/errors in Scala code.
 */
final class ArcanistScalaSBTLinter extends ArcanistExternalLinter {

  public function getInfoName() {
    return 'sbt';
  }

  public function getInfoURI() {
    return 'http://www.scala-sbt.org';
  }

  public function getInfoDescription() {
    return pht('sbt is a build tool for Scala, Java, and more.');
  }

  public function getLinterName() {
    return 'ScalaSBT';
  }

  public function getLinterConfigurationName() {
    return 'scala-sbt';
  }

  public function getDefaultBinary() {
    $prefix = $this->getDeprecatedConfiguration('lint.scala_sbt.prefix');
    $bin = 'sbt';

    if ($prefix) {
      return $prefix.'/'.$bin;
    } else {
      return $bin;
    }
  }

  public function getVersion() {
    // NOTE: `sbt --version` returns a non-zero exit status.
    list($err, $stdout) = exec_manual(
      '%C --version',
      $this->getExecutableCommand());

    $matches = array();
    $regex = '/^sbt launcher version (?P<version>\d+\.\d+\.\d+)$/';
    if (preg_match($regex, $stdout, $matches)) {
      return $matches['version'];
    } else {
      return false;
    }
  }

  public function getInstallInstructions() {
    return pht(
      'Check <http://www.scala-sbt.org> for installation instructions.');
  }

  public function shouldExpectCommandErrors() {
    return true;
  }

  protected function getMandatoryFlags() {
    return array(
      '-Dsbt.log.noformat=true',
      'compile',
    );
  }

  protected function parseLinterOutput($path, $err, $stdout, $stderr) {
    $lines = phutil_split_lines($stdout, false);
    $messages = array();

    foreach ($lines as $line) {
      $matches = null;
      $regex = '/\[(warn|error)\] (.*?):(\d+): (.*?)$/';
      if (!preg_match($regex, $line, $matches)) {
        continue;
      }
      foreach ($matches as $key => $match) {
        $matches[$key] = trim($match);
      }

      $message = new ArcanistLintMessage();
      $message->setPath($matches[2]);
      $message->setLine($matches[3]);
      $message->setCode($this->getLinterName());
      $message->setDescription($matches[4]);

      switch ($matches[1]) {
        case 'error':
          $message->setSeverity(ArcanistLintSeverity::SEVERITY_ERROR);
          break;
        case 'warn':
          $message->setSeverity(ArcanistLintSeverity::SEVERITY_WARNING);
          break;
        default:
          $message->setSeverity(ArcanistLintSeverity::SEVERITY_ADVICE);
          break;
      }

      $messages[] = $message;
    }

    return $messages;
  }

}
