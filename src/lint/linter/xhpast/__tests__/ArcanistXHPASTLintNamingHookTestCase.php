<?php

/**
 * Test cases for @{class:ArcanistXHPASTLintNamingHook}.
 */
final class ArcanistXHPASTLintNamingHookTestCase
  extends PhutilTestCase {

  public function testCaseUtilities() {
    $tests = array(
      'UpperCamelCase'                   => array(1, 0, 0, 0),
      'UpperCamelCaseROFL'               => array(1, 0, 0, 0),

      'lowerCamelCase'                   => array(0, 1, 0, 0),
      'lowerCamelCaseROFL'               => array(0, 1, 0, 0),

      'UPPERCASE_WITH_UNDERSCORES'       => array(0, 0, 1, 0),
      '_UPPERCASE_WITH_UNDERSCORES_'     => array(0, 0, 1, 0),
      '__UPPERCASE__WITH__UNDERSCORES__' => array(0, 0, 1, 0),

      'lowercase_with_underscores'       => array(0, 0, 0, 1),
      '_lowercase_with_underscores_'     => array(0, 0, 0, 1),
      '__lowercase__with__underscores__' => array(0, 0, 0, 1),

      'mixedCASE_NoNsEnSe'               => array(0, 0, 0, 0),
    );

    foreach ($tests as $test => $expect) {
      $this->assertEqual(
        $expect[0],
        ArcanistXHPASTLintNamingHook::isUpperCamelCase($test),
        "UpperCamelCase: '{$test}'");
      $this->assertEqual(
        $expect[1],
        ArcanistXHPASTLintNamingHook::isLowerCamelCase($test),
        "lowerCamelCase: '{$test}'");
      $this->assertEqual(
        $expect[2],
        ArcanistXHPASTLintNamingHook::isUppercaseWithUnderscores($test),
        "UPPERCASE_WITH_UNDERSCORES: '{$test}'");
      $this->assertEqual(
        $expect[3],
        ArcanistXHPASTLintNamingHook::isLowercaseWithUnderscores($test),
        "lowercase_with_underscores: '{$test}'");
    }
  }

  public function testStripUtilities() {
    // Variable stripping.
    $this->assertEqual(
      'stuff',
      ArcanistXHPASTLintNamingHook::stripPHPVariable('stuff'));
    $this->assertEqual(
      'stuff',
      ArcanistXHPASTLintNamingHook::stripPHPVariable('$stuff'));

    // Function/method stripping.
    $this->assertEqual(
      'construct',
      ArcanistXHPASTLintNamingHook::stripPHPFunction('construct'));
    $this->assertEqual(
      'construct',
      ArcanistXHPASTLintNamingHook::stripPHPFunction('__construct'));
  }

}
