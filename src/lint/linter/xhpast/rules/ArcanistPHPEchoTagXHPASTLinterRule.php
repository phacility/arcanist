<?php

final class ArcanistPHPEchoTagXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 7;

  public function getLintName() {
    return pht('Use of Echo Tag `%s`', '<?=');
  }

  public function process(XHPASTNode $root) {
    $tokens = $root->getTokens();

    foreach ($tokens as $token) {
      if ($token->getTypeName() === 'T_OPEN_TAG_WITH_ECHO') {
        $this->raiseLintAtToken(
          $token,
          pht(
            'Avoid the PHP echo short form, `%s`.',
            '<?='));
      }
    }
  }

}
