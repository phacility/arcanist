<?php

final class ArcanistModifierOrderingXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 71;

  public function getLintName() {
    return pht('Modifier Ordering');
  }

  public function getLintSeverity() {
    return ArcanistLintSeverity::SEVERITY_ADVICE;
  }

  public function process(XHPASTNode $root) {
    $this->lintMethodModifierOrdering($root);
    $this->lintPropertyModifierOrdering($root);
  }

  private function lintMethodModifierOrdering(XHPASTNode $root) {
    static $modifiers = array(
      'abstract',
      'final',
      'public',
      'protected',
      'private',
      'static',
    );

    $methods = $root->selectDescendantsOfType('n_METHOD_MODIFIER_LIST');

    foreach ($methods as $method) {
      $modifier_ordering = array_values(
        mpull($method->getChildren(), 'getConcreteString'));
      $expected_modifier_ordering = array_values(
        array_intersect(
          $modifiers,
          $modifier_ordering));

      if (count($modifier_ordering) != count($expected_modifier_ordering)) {
        continue;
      }

      if ($modifier_ordering != $expected_modifier_ordering) {
        $this->raiseLintAtNode(
          $method,
          pht('Non-conventional modifier ordering.'),
          implode(' ', $expected_modifier_ordering));
      }
    }
  }

  private function lintPropertyModifierOrdering(XHPASTNode $root) {
    static $modifiers = array(
      'public',
      'protected',
      'private',
      'static',
    );

    $properties = $root->selectDescendantsOfType(
      'n_CLASS_MEMBER_MODIFIER_LIST');

    foreach ($properties as $property) {
      $modifier_ordering = array_values(
        mpull($property->getChildren(), 'getConcreteString'));
      $expected_modifier_ordering = array_values(
        array_intersect(
          $modifiers,
          $modifier_ordering));

      if (count($modifier_ordering) != count($expected_modifier_ordering)) {
        continue;
      }

      if ($modifier_ordering != $expected_modifier_ordering) {
        $this->raiseLintAtNode(
          $property,
          pht('Non-conventional modifier ordering.'),
          implode(' ', $expected_modifier_ordering));
      }
    }
  }

}
