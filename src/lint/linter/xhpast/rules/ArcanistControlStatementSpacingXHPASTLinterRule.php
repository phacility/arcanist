<?php

final class ArcanistControlStatementSpacingXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 26;

  public function getLintName() {
    return pht('Space After Control Statement');
  }

  public function getLintSeverity() {
    return ArcanistLintSeverity::SEVERITY_WARNING;
  }

  public function process(XHPASTNode $root) {
    foreach ($root->getTokens() as $id => $token) {
      switch ($token->getTypeName()) {
        case 'T_IF':
        case 'T_ELSE':
        case 'T_FOR':
        case 'T_FOREACH':
        case 'T_WHILE':
        case 'T_DO':
        case 'T_SWITCH':
        case 'T_CATCH':
          $after = $token->getNonsemanticTokensAfter();
          if (empty($after)) {
            $this->raiseLintAtToken(
              $token,
              pht('Convention: put a space after control statements.'),
              $token->getValue().' ');
          } else if (count($after) === 1) {
            $space = head($after);

            // If we have an else clause with braces, $space may not be
            // a single white space. e.g.,
            //
            //   if ($x)
            //     echo 'foo'
            //   else          // <- $space is not " " but "\n  ".
            //     echo 'bar'
            //
            // We just require it starts with either a whitespace or a newline.
            if ($token->getTypeName() === 'T_ELSE' ||
                $token->getTypeName() === 'T_DO') {
              break;
            }

            if ($space->isAnyWhitespace() && $space->getValue() !== ' ') {
              $this->raiseLintAtToken(
                $space,
                pht('Convention: put a single space after control statements.'),
                ' ');
            }
          }
          break;
      }
    }
  }

}
