<?php

final class ArcanistPHPOpenTagXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 15;

  public function getLintName() {
    return pht('Expected Open Tag');
  }

  public function process(XHPASTNode $root) {
    $tokens = $root->getTokens();

    foreach ($tokens as $token) {
      if ($token->getTypeName() === 'T_OPEN_TAG') {
        break;
      } else if ($token->getTypeName() === 'T_OPEN_TAG_WITH_ECHO') {
        break;
      } else {
        if (!preg_match('/^#!/', $token->getValue())) {
          $this->raiseLintAtToken(
            $token,
            pht(
              'PHP files should start with `%s`, which may be preceded by '.
              'a `%s` line for scripts.',
              '<?php',
              '#!'));
        }
        break;
      }
    }
  }

}
