<?php

final class ArcanistStaticThisXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 13;

  public function getLintName() {
    return pht('Use of `%s` in Static Context', '$this');
  }

  public function process(XHPASTNode $root) {
    $classes = $root->selectDescendantsOfType('n_CLASS_DECLARATION');

    foreach ($classes as $class) {
      $methods = $class->selectDescendantsOfType('n_METHOD_DECLARATION');

      foreach ($methods as $method) {
        $attributes = $method
          ->getChildByIndex(0, 'n_METHOD_MODIFIER_LIST')
          ->selectDescendantsOfType('n_STRING');

        $method_is_static = false;
        $method_is_abstract = false;

        foreach ($attributes as $attribute) {
          if (strtolower($attribute->getConcreteString()) === 'static') {
            $method_is_static = true;
          }
          if (strtolower($attribute->getConcreteString()) === 'abstract') {
            $method_is_abstract = true;
          }
        }

        if ($method_is_abstract) {
          continue;
        }

        if (!$method_is_static) {
          continue;
        }

        $body = $method->getChildOfType(5, 'n_STATEMENT_LIST');
        $variables = $body->selectDescendantsOfType('n_VARIABLE');

        foreach ($variables as $variable) {
          if ($method_is_static &&
              strtolower($variable->getConcreteString()) === '$this') {
            $this->raiseLintAtNode(
              $variable,
              pht(
                'You can not reference `%s` inside a static method.',
                '$this'));
          }
        }
      }
    }
  }

}
