<?php

/**
 * Uses `sbt compile` to detect various warnings/errors in Scala code.
 *
 * @group linter
 */
final class ArcanistScalaSBTLinter extends ArcanistLinter {

  public function willLintPaths(array $paths) {
    return;
  }

  public function getLinterName() {
    return 'ScalaSBT';
  }

  public function getLintSeverityMap() {
    return array();
  }

  public function getLintNameMap() {
    return array();
  }

  public function canRun() {
    // Check if this looks like a SBT project. If it doesn't, throw, because
    // we rely fairly heavily on `sbt compile` working, below. We don't want
    // to call out to scalac ourselves, because then we'll end up in Class Path
    // Hell. We let the build system handle this for us.
    if (!Filesystem::pathExists('project/Build.scala') &&
      !Filesystem::pathExists('build.sbt')) {
      return false;
    }
    return true;
  }

  private function getSBTPath() {
    $sbt_bin = "sbt";

    // Use the SBT prefix specified in the config file
    $working_copy = $this->getEngine()->getWorkingCopy();
    $prefix = $working_copy->getConfig('lint.scala_sbt.prefix');
    if ($prefix !== null) {
      $sbt_bin = $prefix . $sbt_bin;
    }

    if (!Filesystem::pathExists($sbt_bin)) {

      list($err) = exec_manual('which %s', $sbt_bin);
      if ($err) {
        throw new ArcanistUsageException(
          "SBT does not appear to be installed on this system. Install it or ".
          "add 'lint.scala_sbt.prefix' in your .arcconfig to point to ".
          "the directory where it resides.");
      }
    }

    return $sbt_bin;
  }

  private function getMessageCodeSeverity($type_of_error) {
    switch ($type_of_error) {
      case 'warn':
        return ArcanistLintSeverity::SEVERITY_WARNING;
      case 'error':
        return ArcanistLintSeverity::SEVERITY_ERROR;
    }
  }

  public function lintPath($path) {
    $sbt = $this->getSBTPath();

    // Tell SBT to not use color codes so our regex life is easy.
    // TODO: Should this be "clean compile" instead of "compile"?
    $f = new ExecFuture("%s -Dsbt.log.noformat=true compile", $sbt);
    list($err, $stdout, $stderr) = $f->resolve();

    $lines = explode("\n", $stdout);
    $messages = array();
    foreach ($lines as $line) {
      $matches = null;
      if (!preg_match(
        "/\[(warn|error)\] (.*?):(\d+): (.*?)$/",
        $line,
        $matches)) {
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
      $message->setSeverity($this->getMessageCodeSeverity($matches[1]));
      $this->addLintMessage($message);
    }
  }

}
