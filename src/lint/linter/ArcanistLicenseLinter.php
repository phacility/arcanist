<?php

/**
 * @deprecated
 */
abstract class ArcanistLicenseLinter extends ArcanistLinter {

  const LINT_NO_LICENSE_HEADER = 1;

  public function willLintPaths(array $paths) {
    return;
  }

  public function getLintSeverityMap() {
    return array();
  }

  public function getLintNameMap() {
    return array(
      self::LINT_NO_LICENSE_HEADER   => 'No License Header',
    );
  }

  abstract protected function getLicenseText($copyright_holder);
  abstract protected function getLicensePatterns();

  public function lintPath($path) {
    $copyright_holder = $this->getConfig('copyright_holder');
    if ($copyright_holder === null) {
      $working_copy = $this->getEngine()->getWorkingCopy();
      $copyright_holder = $working_copy->getConfig('copyright_holder');
    }

    if (!$copyright_holder) {
      return;
    }

    $patterns = $this->getLicensePatterns();
    $license = $this->getLicenseText($copyright_holder);

    $data = $this->getData($path);
    $matches = 0;

    foreach ($patterns as $pattern) {
      if (preg_match($pattern, $data, $matches)) {
        $expect = rtrim(implode('', array_slice($matches, 1)))."\n".$license;
        if (trim($matches[0]) != trim($expect)) {
          $this->raiseLintAtOffset(
            0,
            self::LINT_NO_LICENSE_HEADER,
            'This file has a missing or out of date license header.',
            $matches[0],
            ltrim($expect));
        }
        break;
      }
    }
  }
}


