<?php

/**
 * Stifles creativity in choosing imaginative file organization.
 */
final class ArcanistCouserorgLinter extends ArcanistLinter {

  const INVALID_BUNDLE_NAME = 1;
  const MISSING_BUNDLE_NLS = 2;
  const MISPLACED_COMPONENT = 3;
  const MISPLACED_COMPONENT_STYL = 4;

  public function getInfoName() {
    return pht('Couserorg');
  }

  public function getInfoDescription() {
    return pht(
      'Stifles developer creativity by requiring files to conform to '.
      'Coursera Frontend File Organization, by edict of the FCC.');
  }

  public function getLinterName() {
    return 'COURSERORG';
  }

  public function getLinterConfigurationName() {
    return 'courserorg';
  }

  public function shouldLintBinaryFiles() {
    return true;
  }

  public function getLintNameMap() {
    return array(
      self::INVALID_BUNDLE_NAME => pht('Invalid bundle name'),
      self::MISSING_BUNDLE_NLS => pht('Missing NLS for bundle'),
      self::MISPLACED_COMPONENT => pht('Misplaced component'),
      self::MISPLACED_COMPONENT_STYL => pht('Misplaced component styl'),
    );
  }

  public function lintPath($path) {
    if (preg_match('/([^_]*)__styles__\/(.+).styl/', $path, $matches)) {
      $jsxFilename = $matches[1].$matches[2].'.jsx';

      if (!file_exists($jsxFilename))
        $this->raiseLintAtPath(
          self::MISPLACED_COMPONENT_STYL,
          pht(
            'Stylus files in __styles__ must correspond to a React component in the parent directory.'));

    } else if (preg_match('/^static\/bundles\/(.+)\/components/', $path, $matches)) {
      $bundleName = $matches[1];

      if ($bundleName != strtolower($bundleName))
        $this->raiseLintAtPath(
          self::INVALID_BUNDLE_NAME,
          pht(
            'Bundle names must be lowercase.'));

      if (!file_exists('static/nls/'.$bundleName.'.js'))
        $this->raiseLintAtPath(
          self::MISSING_BUNDLE_NLS,
          pht(
            'Every bundle with components must have '.
            'an NLS file.'));

    } else if (preg_match('/^static\/(.+)\/components/', $path)) {
      $this->raiseLintAtPath(
        self::MISPLACED_COMPONENT,
        pht(
          'Component files must always be in a bundle.'));
    }

  }

  public function shouldLintDirectories() {
    return true;
  }

  public function shouldLintSymbolicLinks() {
    return true;
  }

}
