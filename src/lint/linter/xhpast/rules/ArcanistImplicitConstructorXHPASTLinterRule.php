<?php

final class ArcanistImplicitConstructorXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 10;

  public function getLintName() {
    return pht('Implicit Constructor');
  }

  public function process(XHPASTNode $root) {
    $classes = $root->selectDescendantsOfType('n_CLASS_DECLARATION');

    foreach ($classes as $class) {
      $class_name = $class->getChildByIndex(1)->getConcreteString();
      $methods = $class->selectDescendantsOfType('n_METHOD_DECLARATION');

      foreach ($methods as $method) {
        $method_name_token = $method->getChildByIndex(2);
        $method_name = $method_name_token->getConcreteString();

        if (strtolower($class_name) === strtolower($method_name)) {
          $this->raiseLintAtNode(
            $method_name_token,
            pht(
              'Name constructors %s explicitly. This method is a constructor '.
              ' because it has the same name as the class it is defined in.',
              '__construct()'));
        }
      }
    }
  }

}
