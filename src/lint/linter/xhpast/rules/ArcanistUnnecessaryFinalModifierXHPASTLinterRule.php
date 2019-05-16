<?php

final class ArcanistUnnecessaryFinalModifierXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 55;

  public function getLintName() {
    return pht('Unnecessary Final Modifier');
  }

  public function getLintSeverity() {
    return ArcanistLintSeverity::SEVERITY_ADVICE;
  }

  public function process(XHPASTNode $root) {
    $classes = $root->selectDescendantsOfType('n_CLASS_DECLARATION');

    foreach ($classes as $class) {
      if ($class->getChildByIndex(0)->getTypeName() == 'n_EMPTY') {
        continue;
      }
      $attributes = $class->getChildOfType(0, 'n_CLASS_ATTRIBUTES');
      $is_final = false;

      foreach ($attributes->getChildren() as $attribute) {
        if ($attribute->getConcreteString() == 'final') {
          $is_final = true;
          break;
        }
      }

      if (!$is_final) {
        continue;
      }

      $methods = $class->selectDescendantsOfType('n_METHOD_DECLARATION');
      foreach ($methods as $method) {
        $attributes = $method->getChildOfType(0, 'n_METHOD_MODIFIER_LIST');

        foreach ($attributes->getChildren() as $attribute) {
          if ($attribute->getConcreteString() == 'final') {
            $this->raiseLintAtNode(
              $attribute,
              pht(
                'Unnecessary `%s` modifier in `%s` class.',
                'final',
                'final'));
          }
        }
      }
    }
  }

}
