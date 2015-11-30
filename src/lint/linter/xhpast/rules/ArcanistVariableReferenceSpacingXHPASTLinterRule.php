<?php

final class ArcanistVariableReferenceSpacingXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 123;

  public function getLintName() {
    return pht('Variable Reference Spacing');
  }

  public function getLintSeverity() {
    return ArcanistLintSeverity::SEVERITY_WARNING;
  }

  public function process(XHPASTNode $root) {
    $references = $root->selectDescendantsOfType('n_VARIABLE_REFERENCE');

    foreach ($references as $reference) {
      $variable = $reference->getChildByIndex(0);

      list($before, $after) = $variable->getSurroundingNonsemanticTokens();

      if ($before) {
        $this->raiseLintAtOffset(
          head($before)->getOffset(),
          pht('Variable references should not be prefixed with whitespace.'),
          implode('', mpull($before, 'getValue')),
          '');
      }
    }
  }

}
