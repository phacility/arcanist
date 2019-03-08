<?php

/**
 * Base class for linters that used to work but now need to be disabled
 * at client run-time.
 */
abstract class ArcanistNoopLinter extends ArcanistExternalLinter {
  public function getInfoURI() {
    return '';
  }

  public function getInfoDescription() {
    return pht('A former linter that now does nothing at all.');
  }

  public function getLinterName() {
    return 'NOOP';
  }

  public function getLinterConfigurationOptions() {
    return array();
  }

  public function getDefaultBinary() {
    return '/usr/bin/env true';
  }

  public function getInstallInstructions() {
    return '';
  }

  protected function getMandatoryFlags() {
    return array();
  }

  public function getVersion() {
    return '1.0.0';
  }

  protected function parseLinterOutput($path, $err, $stdout, $stderr) {
    return array();
  }
}
