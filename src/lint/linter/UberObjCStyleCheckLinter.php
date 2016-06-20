<?php

/**
 * Uses the Uber Style to format Obj-C code. Based on Square SpaceCommander.
 */
final class UberObjCStyleCheckLinter extends ArcanistExternalLinter {

  private $lintRepoPath;

  public function getInfoName() {
    return 'uber-objc-style-check';
  }

  public function getInfoURI() {
    return 'https://code.uberinternal.com/diffusion/MOLIN/';
  }

  public function getInfoDescription() {
    return pht('Use Uber\'s style guide to format specified files.');
  }

  public function getLinterName() {
    return 'uber-objc-style-check';
  }

  public function getLinterConfigurationName() {
    return 'uber-objc-style-check';
  }

  private function getDefaultLintRepoPath() {
    return $this->getProjectRoot().'/.uber-ios-lint';
  }

  public function getLintRepoPath() {
    return coalesce($this->lintRepoPath, $this->getDefaultLintRepoPath());
  }

  public function setLintRepoPath($path) {
    $this->lintRepoPath = $this->getProjectRoot().'/'.$path;
    return $this;
  }

  public function getLinterConfigurationOptions() {
    $options = array(
      'uberLintRepoPath' => array(
        'type' => 'optional string',
        'help' => pht(
          'Specify a string identifying the path to the'.
          'Uber Mobile iOS Linting repo.'),
      ),
    );

    return $options + parent::getLinterConfigurationOptions();
  }

  public function setLinterConfigurationValue($key, $value) {
    switch ($key) {
      case 'uberLintRepoPath':
        $this->setLintRepoPath($value);
        return;
    }

    return parent::setLinterConfigurationValue($key, $value);
  }

  public function getDefaultBinary() {
    return $this->getLintRepoPath().'/style-check/format.sh';
  }

  public function getInstallInstructions() {
    return pht('Clone the Uber iOS lint repo and set the configuration in '.
               'uberLintRepoPath.');
  }

  public function shouldExpectCommandErrors() {
    return false;
  }

  protected function getMandatoryFlags() {
    return array(
      $this->getProjectRoot(),
    );
  }

  protected function parseLinterOutput($path, $err, $stdout, $stderr) {
    $ok = ($err == 0);

    if (!$ok) {
      return false;
    }

    $fullpath = $this->getProjectRoot().'/'.$path;
    $orig = file_get_contents($fullpath);
    if ($orig == $stdout) {
      return array();
    }

    $message = id(new ArcanistLintMessage())
      ->setPath($path)
      ->setLine(1)
      ->setChar(1)
      ->setGranularity(ArcanistLinter::GRANULARITY_FILE)
      ->setCode('uber-objc-format')
      ->setSeverity(ArcanistLintSeverity::SEVERITY_AUTOFIX)
      ->setName('Autoformatted Obj-C file.')
      ->setDescription("'$path' has been autoformatted.")
      ->setOriginalText($orig)
      ->setReplacementText($stdout);
    return array($message);
  }

}

?>
