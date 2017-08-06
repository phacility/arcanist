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
    $composer_json_path = dirname($path).'/composer.json';
    $composer_lock = phutil_json_decode(
      Filesystem::readFile(dirname($path).'/composer.lock'));

    $expected_hash = null;
    $composer_hash = null;
    if (array_key_exists('hash', $composer_lock)) {
      $composer_hash = md5(Filesystem::readFile($composer_json_path));
      $expected_hash = $composer_lock['hash'];
    }
    if (array_key_exists('content-hash', $composer_lock)) {
      $expected_hash = $this->getComposerContentHash($composer_json_path);
      $composer_hash = $composer_lock['content-hash'];
    }

    if ($composer_hash !== $expected_hash || null === $composer_hash) {
      $this->raiseLintAtPath(
        self::LINT_OUT_OF_DATE,
        pht(
          "The '%s' file seems to be out-of-date. ".
          "You probably need to run `%s`.",
          'composer.lock',
          'composer update'));
    }
  }

  /**
   * See https://github.com/symfony/symfony-installer/pull/196/files
   * for more info
   */
  private function getComposerContentHash($composer_json_file_contents) {
    $content = phutil_json_decode(
      Filesystem::readFile($composer_json_file_contents));

    $relevant_keys = array(
      'name',
      'version',
      'require',
      'require-dev',
      'conflict',
      'replace',
      'provide',
      'minimum-stability',
      'prefer-stable',
      'repositories',
      'extra',
    );

    $relevant_content = array();

    foreach (array_intersect($relevant_keys, array_keys($content)) as $key) {
      $relevant_content[$key] = $content[$key];
    }

    if (isset($content['config']['platform'])) {
      $relevant_content['config']['platform'] = $content['config']['platform'];
    }

    ksort($relevant_content);

    return md5(json_encode($relevant_content));
  }
}
