<?php

final class ArcanistArrayIndexSpacingXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 28;

  public function getLintName() {
    return pht('Spacing Before Array Index');
  }

  public function getLintSeverity() {
    return ArcanistLintSeverity::SEVERITY_WARNING;
  }

  public function process(XHPASTNode $root) {
    $indexes = $root->selectDescendantsOfType('n_INDEX_ACCESS');

    foreach ($indexes as $index) {
      $tokens = $index->getChildByIndex(0)->getTokens();
      $last = array_pop($tokens);
      $trailing = $last->getNonsemanticTokensAfter();
      $trailing_text = implode('', mpull($trailing, 'getValue'));

      if (preg_match('/^ +$/', $trailing_text)) {
        $this->raiseLintAtOffset(
          $last->getOffset() + strlen($last->getValue()),
          pht('Convention: no spaces before index access.'),
          $trailing_text,
          '');
      }
    }
  }

}
