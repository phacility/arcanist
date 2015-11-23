<?php

final class ArcanistAbstractPrivateMethodXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 107;

  public function getLintName() {
    return pht('`%s` Method Cannot Be Declared `%s`', 'abstract', 'private');
  }

  public function process(XHPASTNode $root) {
    $methods = $root->selectDescendantsOfType('n_METHOD_DECLARATION');

    foreach ($methods as $method) {
      $method_modifiers = $method
        ->getChildOfType(0, 'n_METHOD_MODIFIER_LIST')
        ->selectDescendantsOfType('n_STRING');
      $modifiers = array();

      foreach ($method_modifiers as $modifier) {
        $modifiers[strtolower($modifier->getConcreteString())] = true;
      }

      if (idx($modifiers, 'abstract') && idx($modifiers, 'private')) {
        $this->raiseLintAtNode(
          $method,
          pht(
            '`%s` method cannot be declared `%s`. '.
            'This construct will cause a fatal error.',
            'abstract',
            'private'));
      }
    }
  }

}
