<?php

final class ArcanistComposerLinter extends ArcanistLinter {

  const LINT_OUT_OF_DATE = 1;

  public function getInfoName() {
    return pht('Composer Dependency Manager');
  }

  public function getInfoDescription() {
    return pht('A linter for Composer related files.');
  }

  public function getLinterName() {
    return 'COMPOSER';
  }

  public function getLinterConfigurationName() {
    return 'composer';
  }

  public function getLintNameMap() {
    return array(
      self::LINT_OUT_OF_DATE => pht('Lock file out-of-date'),
    );
  }

  public function lintPath($path) {
    switch (basename($path)) {
      case 'composer.json':
        $this->lintComposerJson($path);
        break;
      case 'composer.lock':
        break;
    }
  }

  private function lintComposerJson($path) {
    $composer_hash = md5(Filesystem::readFile(dirname($path).'/composer.json'));
    $composer_lock = phutil_json_decode(
      Filesystem::readFile(dirname($path).'/composer.lock'));

    if ($composer_hash !== $composer_lock['hash']) {
      $this->raiseLintAtPath(
        self::LINT_OUT_OF_DATE,
        pht(
          "The '%s' file seems to be out-of-date. ".
          "You probably need to run `%s`.",
          'composer.lock',
          'composer update'));
    }
  }

}
