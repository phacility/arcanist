<?php

final class ArcanistSelfClassReferenceXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 95;

  public function getLintName() {
    return pht('Self Class Reference');
  }

  public function getLintSeverity() {
    return ArcanistLintSeverity::SEVERITY_WARNING;
  }

  public function process(XHPASTNode $root) {
    $class_declarations = $root->selectDescendantsOfType('n_CLASS_DECLARATION');

    foreach ($class_declarations as $class_declaration) {
      if ($class_declaration->getChildByIndex(1)->getTypeName() == 'n_EMPTY') {
        continue;
      }
      $class_name = $class_declaration
        ->getChildOfType(1, 'n_CLASS_NAME')
        ->getConcreteString();
      $instantiations = $class_declaration
        ->selectDescendantsOfType('n_NEW');

      foreach ($instantiations as $instantiation) {
        $type = $instantiation->getChildByIndex(0);

        if ($type->getTypeName() != 'n_CLASS_NAME') {
          continue;
        }

        if (strtolower($type->getConcreteString()) == strtolower($class_name)) {
          $this->raiseLintAtNode(
            $type,
            pht(
              'Use `%s` to instantiate the current class.',
              'self'),
            'self');
        }
      }
    }
  }

}
