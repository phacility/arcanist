<?php

final class ArcanistAbstractMethodBodyXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 108;

  public function getLintName() {
    return pht('`%s` Method Cannot Contain Body', 'abstract');
  }

  public function process(XHPASTNode $root) {
    $methods = $root->selectDescendantsOfType('n_METHOD_DECLARATION');

    foreach ($methods as $method) {
      $modifiers = $this->getModifiers($method);
      $body = $method->getChildByIndex(5);

      if (idx($modifiers, 'abstract') && $body->getTypeName() != 'n_EMPTY') {
        $this->raiseLintAtNode(
          $body,
          pht(
            '`%s` methods cannot contain a body. This construct will '.
            'cause a fatal error.',
            'abstract'));
      }
    }
  }

}
