<?php

final class ArcanistPHPShortTagXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 6;

  public function getLintName() {
    return pht('Use of Short Tag `%s`', '<?');
  }

  public function process(XHPASTNode $root) {
    $tokens = $root->getTokens();

    foreach ($tokens as $token) {
      if ($token->getTypeName() === 'T_OPEN_TAG') {
        if (trim($token->getValue()) === '<?') {
          $this->raiseLintAtToken(
            $token,
            pht(
              'Use the full form of the PHP open tag, `%s`.',
              '<?php'),
            "<?php\n");
        }
        break;
      }
    }
  }

}
