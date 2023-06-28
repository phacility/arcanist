<?php

final class ArcanistRuntimeConfigurationSource
  extends ArcanistDictionaryConfigurationSource {

  public function __construct(array $argv) {
    $map = array();
    foreach ($argv as $raw) {
      $parts = explode('=', $raw, 2);
      if (count($parts) !== 2) {
        throw new PhutilArgumentUsageException(
          pht(
            'Configuration option "%s" is not valid. Configuration options '.
            'passed with command line flags must be in the form "name=value".',
            $raw));
      }

      list($key, $value) = $parts;
      if (isset($map[$key])) {
        throw new PhutilArgumentUsageException(
          pht(
            'Configuration option "%s" was provided multiple times with '.
            '"--config" flags. Specify each option no more than once.',
            $key));
      }

      $map[$key] = $value;
    }

    parent::__construct($map);
  }

  public function didReadUnknownOption(ArcanistRuntime $runtime, $key) {
    throw new PhutilArgumentUsageException(
      pht(
        'Configuration option ("%s") specified with "--config" flag is not '.
        'a recognized option.',
        $key));
  }

  public function getSourceDisplayName() {
    return pht('Runtime "--config" Flags');
  }

  public function isStringSource() {
    return true;
  }

}
