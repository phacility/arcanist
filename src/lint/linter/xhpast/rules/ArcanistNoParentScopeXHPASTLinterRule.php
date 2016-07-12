<?php

final class ArcanistNoParentScopeXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 64;

  public function getLintName() {
    return pht('No Parent Scope');
  }

  public function process(XHPASTNode $root) {
    $classes = $root->selectDescendantsOfType('n_CLASS_DECLARATION');

    foreach ($classes as $class) {
      $methods = $class->selectDescendantsOfType('n_METHOD_DECLARATION');

      if ($class->getChildByIndex(2)->getTypeName() == 'n_EXTENDS_LIST') {
        continue;
      }

      foreach ($methods as $method) {
        $static_accesses = $method
          ->selectDescendantsOfType('n_CLASS_STATIC_ACCESS');

        foreach ($static_accesses as $static_access) {
          $called_class = $static_access->getChildByIndex(0);

          if ($called_class->getTypeName() != 'n_CLASS_NAME') {
            continue;
          }

          if ($called_class->getConcreteString() == 'parent') {
            $this->raiseLintAtNode(
              $static_access,
              pht(
                'Cannot access `%s` when current class scope has no parent.',
                'parent::'));
          }
        }
      }
    }
  }

}
