<?php

final class ArcanistUselessOverridingMethodXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 63;

  public function getLintName() {
    return pht('Useless Overriding Method');
  }

  public function getLintSeverity() {
    return ArcanistLintSeverity::SEVERITY_ADVICE;
  }

  public function process(XHPASTNode $root) {
    $methods = $root->selectDescendantsOfType('n_METHOD_DECLARATION');

    foreach ($methods as $method) {
      $method_name = $method
        ->getChildOfType(2, 'n_STRING')
        ->getConcreteString();

      $parameter_list = $method
        ->getChildOfType(3, 'n_DECLARATION_PARAMETER_LIST');
      $parameters = array();

      foreach ($parameter_list->getChildren() as $parameter) {
        $parameter = $parameter->getChildByIndex(1);

        if ($parameter->getTypeName() == 'n_VARIABLE_REFERENCE') {
          $parameter = $parameter->getChildOfType(0, 'n_VARIABLE');
        }

        $parameters[] = $parameter->getConcreteString();
      }

      $statements = $method->getChildByIndex(5);

      if ($statements->getTypeName() != 'n_STATEMENT_LIST') {
        continue;
      }

      if (count($statements->getChildren()) != 1) {
        continue;
      }

      $statement = $statements
        ->getChildOfType(0, 'n_STATEMENT')
        ->getChildByIndex(0);

      if ($statement->getTypeName() == 'n_RETURN') {
        $statement = $statement->getChildByIndex(0);
      }

      if ($statement->getTypeName() != 'n_FUNCTION_CALL') {
        continue;
      }

      $function = $statement->getChildByIndex(0);

      if ($function->getTypeName() != 'n_CLASS_STATIC_ACCESS') {
        continue;
      }

      $called_class  = $function->getChildOfType(0, 'n_CLASS_NAME');
      $called_method = $function->getChildOfType(1, 'n_STRING');

      if ($called_class->getConcreteString() != 'parent') {
        continue;
      } else if ($called_method->getConcreteString() != $method_name) {
        continue;
      }

      $params = $statement
        ->getChildOfType(1, 'n_CALL_PARAMETER_LIST')
        ->getChildren();

      foreach ($params as $param) {
        if ($param->getTypeName() != 'n_VARIABLE') {
          continue 2;
        }

        $expected = array_shift($parameters);

        if ($param->getConcreteString() != $expected) {
          continue 2;
        }
      }

      $this->raiseLintAtNode(
        $method,
        pht('Useless overriding method.'));
    }
  }

}
