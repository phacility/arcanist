<?php

/**
 * Facilitates implementation of test cases for
 * @{class:ArcanistXHPASTLinterRule}s.
 */
abstract class ArcanistXHPASTLinterRuleTestCase
  extends ArcanistLinterTestCase {

  final protected function getLinter() {
    // Always include this rule so we get good messages if a test includes
    // a syntax error. No normal test should contain syntax errors.
    $syntax_rule = new ArcanistSyntaxErrorXHPASTLinterRule();

    $test_rule = $this->getLinterRule();

    $rules = array(
      $syntax_rule,
      $test_rule,
    );

    return id(new ArcanistXHPASTLinter())
      ->setRules($rules);
  }

  /**
   * Returns an instance of the linter rule being tested.
   *
   * @return ArcanistXHPASTLinterRule
   */
  protected function getLinterRule() {
    $this->assertExecutable('xhpast');

    $class = get_class($this);
    $matches = null;

    if (!preg_match('/^(\w+XHPASTLinterRule)TestCase$/', $class, $matches) ||
        !is_subclass_of($matches[1], 'ArcanistXHPASTLinterRule')) {
      throw new Exception(pht('Unable to infer linter rule class name.'));
    }

    return newv($matches[1], array());
  }

}
