<?php

final class ArcanistSelfMemberReferenceXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 57;

  public function getLintName() {
    return pht('Self Member Reference');
  }

  public function getLintSeverity() {
    return ArcanistLintSeverity::SEVERITY_WARNING;
  }

  public function process(XHPASTNode $root) {
    $class_declarations = $root->selectDescendantsOfType('n_CLASS_DECLARATION');

    foreach ($class_declarations as $class_declaration) {
      $class_name = $class_declaration
        ->getChildOfType(1, 'n_CLASS_NAME')
        ->getConcreteString();
      $class_static_accesses = $class_declaration
        ->selectDescendantsOfType('n_CLASS_STATIC_ACCESS');
      $closures = $this->getAnonymousClosures($class_declaration);

      foreach ($class_static_accesses as $class_static_access) {
        $class_ref = $class_static_access->getChildByIndex(0);

        if ($class_ref->getTypeName() != 'n_CLASS_NAME') {
          continue;
        }
        $class_ref_name = $class_ref->getConcreteString();

        if (strtolower($class_name) == strtolower($class_ref_name)) {
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
                'Use `%s` for local static member references.',
                'self::'),
              'self');
          }
        }
      }
    }
  }

}
