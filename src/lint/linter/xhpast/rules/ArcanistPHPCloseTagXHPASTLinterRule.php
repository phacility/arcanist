<?php

final class ArcanistPHPCloseTagXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 8;

  public function getLintName() {
    return pht('Use of Close Tag "%s"', '?>');
  }

  public function process(XHPASTNode $root) {
    foreach ($root->selectTokensOfType('T_CLOSE_TAG') as $token) {
      $this->raiseLintAtToken(
        $token,
        pht('Do not use the PHP closing tag, "%s".', '?>'));
    }
  }

}
