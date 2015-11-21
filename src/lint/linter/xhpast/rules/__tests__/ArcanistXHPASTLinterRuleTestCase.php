<?php

/**
 * Facilitates implementation of test cases for
 * @{class:ArcanistXHPASTLinterRule}s.
 */
abstract class ArcanistXHPASTLinterRuleTestCase
  extends ArcanistLinterTestCase {

  final protected function getLinter() {
    return id(new ArcanistXHPASTLinter())
      ->setRules(array($this->getLinterRule()));
  }

  /**
   * Returns an instance of the linter rule being tested.
   *
   * @return ArcanistXHPASTLinterRule
   */
  protected function getLinterRule() {
    $class = get_class($this);
    $matches = null;

    if (!preg_match('/^(\w+XHPASTLinterRule)TestCase$/', $class, $matches) ||
        !is_subclass_of($matches[1], 'ArcanistXHPASTLinterRule')) {
      throw new Exception(pht('Unable to infer linter rule class name.'));
    }

    return newv($matches[1], array());
  }

}
