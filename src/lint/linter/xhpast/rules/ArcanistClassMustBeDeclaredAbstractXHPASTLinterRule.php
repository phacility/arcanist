<?php

final class ArcanistClassMustBeDeclaredAbstractXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 113;

  public function getLintName() {
    return pht(
      '`%s` Containing `%s` Methods Must Be Declared `%s`',
      'class',
      'abstract',
      'abstract');
  }

  public function process(XHPASTNode $root) {
    $classes = $root->selectDescendantsOfType('n_CLASS_DECLARATION');

    foreach ($classes as $class) {
      $class_modifiers = $this->getModifiers($class);

      $abstract_methods = array();
      $methods = $class->selectDescendantsOfType('n_METHOD_DECLARATION');

      foreach ($methods as $method) {
        $method_modifiers = $this->getModifiers($method);

        if (idx($method_modifiers, 'abstract')) {
          $abstract_methods[] = $method;
        }
      }

      if (!idx($class_modifiers, 'abstract') && $abstract_methods) {
        $this->raiseLintAtNode(
          $class,
          pht(
            'Class contains %s %s method(s) and must therefore '.
            'be declared `%s`.',
            phutil_count($abstract_methods),
            'abstract',
            'abstract'));
      }
    }
  }

}
