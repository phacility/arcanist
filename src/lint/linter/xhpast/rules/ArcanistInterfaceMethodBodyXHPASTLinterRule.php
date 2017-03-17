<?php

final class ArcanistInterfaceMethodBodyXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 114;

  public function getLintName() {
    return pht('`%s` Method Cannot Contain Body', 'interface');
  }

  public function process(XHPASTNode $root) {
    $interfaces = $root->selectDescendantsOfType('n_INTERFACE_DECLARATION');

    foreach ($interfaces as $interface) {
      $methods = $interface->selectDescendantsOfType('n_METHOD_DECLARATION');

      foreach ($methods as $method) {
        $body = $method->getChildByIndex(6);

        if ($body->getTypeName() != 'n_EMPTY') {
          $this->raiseLintAtNode(
            $body,
            pht(
              '`%s` methods cannot contain a body. This construct will '.
              'cause a fatal error.',
              'interface'));
        }
      }
    }
  }

}
