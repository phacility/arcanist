<?php

final class ArcanistCallParenthesesXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 37;

  public function getLintName() {
    return pht('Call Formatting');
  }

  public function getLintSeverity() {
    return ArcanistLintSeverity::SEVERITY_WARNING;
  }

  public function process(XHPASTNode $root) {
    $nodes = $root->selectDescendantsOfTypes(array(
      'n_ARRAY_LITERAL',
      'n_FUNCTION_CALL',
      'n_METHOD_CALL',
      'n_LIST',
    ));

    foreach ($nodes as $node) {
      switch ($node->getTypeName()) {
        case 'n_ARRAY_LITERAL':
          if (head($node->getTokens())->getTypeName() == '[') {
            // Short array syntax.
            continue 2;
          }

          $params = $node->getChildOfType(0, 'n_ARRAY_VALUE_LIST');
          break;

        case 'n_FUNCTION_CALL':
        case 'n_METHOD_CALL':
          $params = $node->getChildOfType(1, 'n_CALL_PARAMETER_LIST');
          break;

        case 'n_LIST':
          $params = $node->getChildOfType(0, 'n_ASSIGNMENT_LIST');
          break;

        default:
          throw new Exception(
            pht("Unexpected node of type '%s'!", $node->getTypeName()));
      }

      $tokens = $params->getTokens();
      $first  = head($tokens);


      $leading = $first->getNonsemanticTokensBefore();
      $leading_text = implode('', mpull($leading, 'getValue'));
      if (preg_match('/^\s+$/', $leading_text)) {
        $this->raiseLintAtOffset(
          $first->getOffset() - strlen($leading_text),
          pht('Convention: no spaces before opening parentheses.'),
          $leading_text,
          '');
      }

      // If the last parameter of a call is a HEREDOC, don't apply this rule.
      $params = $params->getChildren();

      if ($params) {
        $last_param = last($params);
        if ($last_param->getTypeName() === 'n_HEREDOC') {
          continue;
        }
      }

      $tokens = $node->getTokens();
      $last = array_pop($tokens);

      if ($node->getTypeName() == 'n_ARRAY_LITERAL') {
        continue;
      }

      $trailing = $last->getNonsemanticTokensBefore();
      $trailing_text = implode('', mpull($trailing, 'getValue'));
      if (preg_match('/^\s+$/', $trailing_text)) {
        $this->raiseLintAtOffset(
          $last->getOffset() - strlen($trailing_text),
          pht('Convention: no spaces before closing parentheses.'),
          $trailing_text,
          '');
      }
    }
  }

}
