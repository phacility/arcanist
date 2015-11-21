<?php

final class ArcanistClassExtendsObjectXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 88;

  public function getLintName() {
    return pht('Class Not Extending %s', 'Phobject');
  }

  public function getLintSeverity() {
    return ArcanistLintSeverity::SEVERITY_DISABLED;
  }

  public function process(XHPASTNode $root) {
    $classes = $root->selectDescendantsOfType('n_CLASS_DECLARATION');

    foreach ($classes as $class) {
      // TODO: This doesn't quite work for namespaced classes (see T8534).
      $name    = $class->getChildOfType(1, 'n_CLASS_NAME');
      $extends = $class->getChildByIndex(2);

      if ($name->getConcreteString() == 'Phobject') {
        continue;
      }

      if ($extends->getTypeName() == 'n_EMPTY') {
        $this->raiseLintAtNode(
          $class,
          pht(
            'Classes should extend from %s or from some other class. '.
            'All classes (except for %s itself) should have a base class.',
            'Phobject',
            'Phobject'));
      }
    }
  }

}
