<?php

final class ArcanistInterfaceAbstractMethodXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 118;

  public function getLintName() {
    return pht('`%s` Methods Cannot Be Marked `%s`', 'interface', 'abstract');
  }

  public function process(XHPASTNode $root) {
    $interfaces = $root->selectDescendantsOfType('n_INTERFACE_DECLARATION');

    foreach ($interfaces as $interface) {
      $methods = $interface->selectDescendantsOfType('n_METHOD_DECLARATION');

      foreach ($methods as $method) {
        $modifiers = $this->getModifiers($method);

        if (idx($modifiers, 'abstract')) {
          $this->raiseLintAtNode(
            $method,
            pht(
              '`%s` methods cannot be marked as `%s`. This construct will '.
              'cause a fatal error.',
              'interface',
              'abstract'));
        }
      }
    }
  }

}
