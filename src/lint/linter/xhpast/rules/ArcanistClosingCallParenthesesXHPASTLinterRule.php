<?php

final class ArcanistClosingCallParenthesesXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 37;

  public function getLintName() {
    return pht('Call Formatting');
  }

  public function getLintSeverity() {
    return ArcanistLintSeverity::SEVERITY_WARNING;
  }

  public function process(XHPASTNode $root) {
    $calls = $root->selectDescendantsOfTypes(array(
      'n_FUNCTION_CALL',
      'n_METHOD_CALL',
    ));

    foreach ($calls as $call) {
      // If the last parameter of a call is a HEREDOC, don't apply this rule.
      $params = $call
        ->getChildOfType(1, 'n_CALL_PARAMETER_LIST')
        ->getChildren();

      if ($params) {
        $last_param = last($params);
        if ($last_param->getTypeName() === 'n_HEREDOC') {
          continue;
        }
      }

      $tokens = $call->getTokens();
      $last = array_pop($tokens);

      $trailing = $last->getNonsemanticTokensBefore();
      $trailing_text = implode('', mpull($trailing, 'getValue'));
      if (preg_match('/^\s+$/', $trailing_text)) {
        $this->raiseLintAtOffset(
          $last->getOffset() - strlen($trailing_text),
          pht('Convention: no spaces before closing parenthesis in calls.'),
          $trailing_text,
          '');
      }
    }
  }

}
