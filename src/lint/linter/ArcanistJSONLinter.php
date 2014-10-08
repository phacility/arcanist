<?php

/**
 * A linter for JSON files.
 */
final class ArcanistJSONLinter extends ArcanistLinter {

  public function getInfoName() {
    return 'JSON Lint';
  }

  public function getInfoDescription() {
    return pht('Detect syntax errors in JSON files.');
  }

  public function getLinterName() {
    return 'JSON';
  }

  public function getLinterConfigurationName() {
    return 'json';
  }

  public function lintPath($path) {
    $data = $this->getData($path);

    try {
      $parser = new PhutilJSONParser();
      $parser->parse($data);
    } catch (PhutilJSONParserException $ex) {
      $this->raiseLintAtLine(
        $ex->getSourceLine(),
        $ex->getSourceChar(),
        $this->getLinterName(),
        $ex->getMessage());
    }
  }

}
