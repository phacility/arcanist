<?php

final class ArcanistArrayValueXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 76;

  public function getLintName() {
    return pht('Array Element');
  }

  public function getLintSeverity() {
    return ArcanistLintSeverity::SEVERITY_WARNING;
  }

  public function process(XHPASTNode $root) {
    $arrays = $root->selectDescendantsOfType('n_ARRAY_LITERAL');

    foreach ($arrays as $array) {
      $array_values = $array
        ->getChildOfType(0, 'n_ARRAY_VALUE_LIST')
        ->getChildrenOfType('n_ARRAY_VALUE');

      if (!$array_values) {
        // There is no need to check an empty array.
        continue;
      }

      $multiline = $array->getLineNumber() != $array->getEndLineNumber();

      if (!$multiline) {
        continue;
      }

      foreach ($array_values as $value) {
        list($before, $after) = $value->getSurroundingNonsemanticTokens();

        if (strpos(implode('', mpull($before, 'getValue')), "\n") === false) {
          if (last($before) && last($before)->isAnyWhitespace()) {
            $token = last($before);
            $replacement = "\n".$value->getIndentation();
          } else {
            $token = head($value->getTokens());
            $replacement = "\n".$value->getIndentation().$token->getValue();
          }

          $this->raiseLintAtToken(
            $token,
            pht('Array elements should each occupy a single line.'),
            $replacement);
        }
      }
    }
  }

}
