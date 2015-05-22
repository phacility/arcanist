<?php

/**
 * A linter for JSON files.
 */
final class ArcanistJSONLinter extends ArcanistLinter {

  const LINT_PARSE_ERROR = 1;

  public function getInfoName() {
    return pht('JSON Lint');
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

  public function getLintNameMap() {
    return array(
      self::LINT_PARSE_ERROR => pht('Parse Error'),
    );
  }

  protected function canCustomizeLintSeverities() {
    return false;
  }

  public function lintPath($path) {
    $data = $this->getData($path);

    try {
      id(new PhutilJSONParser())->parse($data);
    } catch (PhutilJSONParserException $ex) {
      $this->raiseLintAtLine(
        $ex->getSourceLine(),
        $ex->getSourceChar(),
        self::LINT_PARSE_ERROR,
        $ex->getMessage());
    }
  }

}
