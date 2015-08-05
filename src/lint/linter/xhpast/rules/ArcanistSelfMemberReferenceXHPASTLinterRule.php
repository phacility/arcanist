<?php

final class ArcanistSelfMemberReferenceXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 57;

  public function getLintName() {
    return pht('Self Member Reference');
  }

  public function getLintSeverity() {
    return ArcanistLintSeverity::SEVERITY_ADVICE;
  }

  public function process(XHPASTNode $root) {
    $class_declarations = $root->selectDescendantsOfType('n_CLASS_DECLARATION');

    foreach ($class_declarations as $class_declaration) {
      $class_name = $class_declaration
        ->getChildOfType(1, 'n_CLASS_NAME')
        ->getConcreteString();

      $class_static_accesses = $class_declaration
        ->selectDescendantsOfType('n_CLASS_STATIC_ACCESS');

      foreach ($class_static_accesses as $class_static_access) {
        $double_colons = $class_static_access
          ->selectTokensOfType('T_PAAMAYIM_NEKUDOTAYIM');
        $class_ref = $class_static_access->getChildByIndex(0);

        if ($class_ref->getTypeName() != 'n_CLASS_NAME') {
          continue;
        }
        $class_ref_name = $class_ref->getConcreteString();

        if (strtolower($class_name) == strtolower($class_ref_name)) {
          $this->raiseLintAtNode(
            $class_ref,
            pht(
              'Use `%s` for local static member references.',
              'self::'),
            'self');
        }

        static $self_refs = array(
          'parent',
          'self',
          'static',
        );

        if (!in_array(strtolower($class_ref_name), $self_refs)) {
          continue;
        }

        if ($class_ref_name != strtolower($class_ref_name)) {
          $this->raiseLintAtNode(
            $class_ref,
            pht('PHP keywords should be lowercase.'),
            strtolower($class_ref_name));
        }
      }
    }

    $double_colons = $root->selectTokensOfType('T_PAAMAYIM_NEKUDOTAYIM');

    foreach ($double_colons as $double_colon) {
      $tokens = $double_colon->getNonsemanticTokensBefore() +
        $double_colon->getNonsemanticTokensAfter();

      foreach ($tokens as $token) {
        if ($token->isAnyWhitespace()) {
          if (strpos($token->getValue(), "\n") !== false) {
            continue;
          }

          $this->raiseLintAtToken(
            $token,
            pht('Unnecessary whitespace around double colon operator.'),
            '');
        }
      }
    }
  }

}
