<?php

final class ArcanistScalastyleLinter extends ArcanistExternalLinter {

  private $jarPath = null;
  private $configPath = null;

  private function usesScalaStyle() {
    return $this->jarPath === null;
  }

  public function getInfoURI() {
    return 'http://www.scalastyle.org/';
  }

  public function getInfoDescription() {
    return 'Scalastyle linter for Scala code';
  }

  public function getLinterName() {
    return 'scalastyle';
  }

  public function getLinterConfigurationName() {
    return 'scalastyle';
  }

  public function getDefaultBinary() {
    if ($this->usesScalaStyle()) {
      return 'scalastyle';
    } else {
      return 'java';
    }
  }

  public function getInstallInstructions() {
    return 'See http://www.scalastyle.org/command-line.html or run "brew install scalastyle" on OS X';
  }

  public function shouldExpectCommandErrors() {
    return true;
  }

  protected function getMandatoryFlags() {
    if ($this->configPath === null) {
      throw new ArcanistUsageException(
        pht('Scalastyle config XML path must be configured.'));
    }

    $options = array(
      '--config', $this->configPath,
      '--quiet', 'true');

    if (!$this->usesScalaStyle()) {
      array_unshift($options, '-jar', $this->jarPath);
    }

    return $options;
  }

  protected function getDefaultFlags() {
    return array();
  }

  protected function parseLinterOutput($path, $err, $stdout, $stderr) {

    $messages = array();

    $output = trim($stdout);
    if (strlen($output) === 0) {
      return $messages;
    }

    $lines = explode(PHP_EOL, $output);

    foreach ($lines as $line) {
      $lintMessage = id(new ArcanistLintMessage())
        ->setPath($path)
        ->setCode($this->getLinterName());

      $matches = array();
      if (preg_match('/^([a-z]+)/', $line, $matches)) {
        switch ($matches[1]) {
          case 'warning':
            $lintMessage->setSeverity(ArcanistLintSeverity::SEVERITY_WARNING);
            break;
          case 'error':
            $lintMessage->setSeverity(ArcanistLintSeverity::SEVERITY_ERROR);
            break;
        }
      }

      $matches = array();
      if (preg_match('/message=([^=]+ )/', $line, $matches)) {
        $lintMessage->setDescription(trim($matches[1]));
      } else if (preg_match('/message=([^=]+$)/', $line, $matches)) {
        $lintMessage->setDescription(trim($matches[1]));
      }

      $matches = array();
      if (preg_match('/line=([^=]+ )/', $line, $matches)) {
        $lintMessage->setLine(trim($matches[1]));
      } else if (preg_match('/line=([^=]+$)/', $line, $matches)) {
        $lintMessage->setLine(trim($matches[1]));
      }

      $matches = array();
      if (preg_match('/column=([^=]+ )/', $line, $matches)) {
        $lintMessage->setChar(trim($matches[1]));
      } else if (preg_match('/column=([^=]+$)/', $line, $matches)) {
        $lintMessage->setChar(trim($matches[1]));
      }

      $messages[] = $lintMessage;
    }

    return $messages;
  }

  public function getLinterConfigurationOptions() {
    $options = array(
      'jar' => array(
        'type' => 'optional string | list<string>',
        'help' => pht(
          'Specify a string (or list of strings) identifying the Scalastyle '.
          'JAR file.')
      ),
      'config' => array(
        'type' => 'optional string | list<string>',
        'help' => pht(
          'Specify a string (or list of strings) identifying the Scalastyle '.
          'config XML file.')
      ),
    );

    return $options + parent::getLinterConfigurationOptions();
  }

  public function setLinterConfigurationValue($key, $value) {
    switch ($key) {
      case 'jar':
        $working_copy = $this->getEngine()->getWorkingCopy();
        $root = $working_copy->getProjectRoot();

        foreach ((array)$value as $path) {
          if (Filesystem::pathExists($path)) {
            $this->jarPath = $path;
            return;
          }

          $path = Filesystem::resolvePath($path, $root);

          if (Filesystem::pathExists($path)) {
            $this->jarPath = $path;
            return;
          }
        }

        throw new ArcanistUsageException(
          pht('None of the configured Scalastyle JARs can be located.'));

      case 'config':
        $working_copy = $this->getEngine()->getWorkingCopy();
        $root = $working_copy->getProjectRoot();

        foreach ((array)$value as $path) {
          if (Filesystem::pathExists($path)) {
            $this->configPath = $path;
            return;
          }

          $path = Filesystem::resolvePath($path, $root);

          if (Filesystem::pathExists($path)) {
            $this->configPath = $path;
            return;
          }
        }

        throw new ArcanistUsageException(
          pht('None of the configured Scalastyle configs can be located.'));
    }

    return parent::setLinterConfigurationValue($key, $value);
  }

}
