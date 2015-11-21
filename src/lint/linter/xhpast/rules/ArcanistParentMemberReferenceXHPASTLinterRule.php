<?php

final class ArcanistParentMemberReferenceXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 83;

  public function getLintName() {
    return pht('Parent Member Reference');
  }

  public function getLintSeverity() {
    return ArcanistLintSeverity::SEVERITY_WARNING;
  }

  public function process(XHPASTNode $root) {
    $class_declarations = $root->selectDescendantsOfType('n_CLASS_DECLARATION');

    foreach ($class_declarations as $class_declaration) {
      $extends_list = $class_declaration
        ->getChildByIndex(2);
      $parent_class = null;

      if ($extends_list->getTypeName() == 'n_EXTENDS_LIST') {
        $parent_class = $extends_list
          ->getChildOfType(0, 'n_CLASS_NAME')
          ->getConcreteString();
      }

      if (!$parent_class) {
        continue;
      }

      $class_static_accesses = $class_declaration
        ->selectDescendantsOfType('n_CLASS_STATIC_ACCESS');
      $closures = $this->getAnonymousClosures($class_declaration);

      foreach ($class_static_accesses as $class_static_access) {
        $double_colons = $class_static_access
          ->selectTokensOfType('T_PAAMAYIM_NEKUDOTAYIM');
        $class_ref = $class_static_access->getChildByIndex(0);

        if ($class_ref->getTypeName() != 'n_CLASS_NAME') {
          continue;
        }
        $class_ref_name = $class_ref->getConcreteString();

        if (strtolower($parent_class) == strtolower($class_ref_name)) {
          $in_closure = false;

          foreach ($closures as $closure) {
            if ($class_ref->isDescendantOf($closure)) {
              $in_closure = true;
              break;
            }
          }

          if (version_compare($this->version, '5.4.0', '>=') || !$in_closure) {
            $this->raiseLintAtNode(
              $class_ref,
              pht(
                'Use `%s` to call parent method.',
                'parent::'),
              'parent');
          }
        }
      }
    }
  }

}
