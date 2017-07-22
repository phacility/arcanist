<?php

final class ArcanistPHPCloseTagXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 8;

  public function getLintName() {
    return pht('Use of Close Tag `%s`', '?>');
  }

  public function process(XHPASTNode $root) {
    $inline_html = $root->selectDescendantsOfType('n_INLINE_HTML');

    if (count($inline_html) > 0) {
      return;
    }

    foreach ($root->selectTokensOfType('T_CLOSE_TAG') as $token) {
      $this->raiseLintAtToken(
        $token,
        pht(
          'Do not use the PHP closing tag, `%s`.',
          '?>'));
    }
  }

}
