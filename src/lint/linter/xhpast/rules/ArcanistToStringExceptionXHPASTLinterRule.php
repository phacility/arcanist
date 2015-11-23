<?php

final class ArcanistToStringExceptionXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 67;

  public function getLintName() {
    return pht('Throwing Exception in `%s` Method', '__toString');
  }

  public function process(XHPASTNode $root) {
    $methods = $root->selectDescendantsOfType('n_METHOD_DECLARATION');

    foreach ($methods as $method) {
      $name = $method
        ->getChildOfType(2, 'n_STRING')
        ->getConcreteString();

      if ($name != '__toString') {
        continue;
      }

      $statements = $method->getChildByIndex(5);

      if ($statements->getTypeName() != 'n_STATEMENT_LIST') {
        continue;
      }

      $throws = $statements->selectDescendantsOfType('n_THROW');

      foreach ($throws as $throw) {
        $this->raiseLintAtNode(
          $throw,
          pht(
            'It is not possible to throw an `%s` from within the `%s` method.',
            'Exception',
            '__toString'));
      }
    }
  }

}
