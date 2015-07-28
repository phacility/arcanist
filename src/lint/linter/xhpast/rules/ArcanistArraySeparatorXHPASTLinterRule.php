<?php

final class ArcanistArraySeparatorXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 48;

  public function getLintName() {
    return pht('Array Separator');
  }

  public function getLintSeverity() {
    return ArcanistLintSeverity::SEVERITY_ADVICE;
  }

  public function process(XHPASTNode $root) {
    $arrays = $root->selectDescendantsOfType('n_ARRAY_LITERAL');

    foreach ($arrays as $array) {
      $value_list = $array->getChildOfType(0, 'n_ARRAY_VALUE_LIST');
      $values = $value_list->getChildrenOfType('n_ARRAY_VALUE');

      if (!$values) {
        // There is no need to check an empty array.
        continue;
      }

      $multiline = $array->getLineNumber() != $array->getEndLineNumber();

      $value = last($values);
      $after = last($value->getTokens())->getNextToken();

      if ($multiline) {
        if (!$after || $after->getValue() != ',') {
          if ($value->getChildByIndex(1)->getTypeName() == 'n_HEREDOC') {
            continue;
          }

          list($before, $after) = $value->getSurroundingNonsemanticTokens();
          $after = implode('', mpull($after, 'getValue'));

          $original    = $value->getConcreteString();
          $replacement = $value->getConcreteString().',';

          if (strpos($after, "\n") === false) {
            $original    .= $after;
            $replacement .= $after."\n".$array->getIndentation();
          }

          $this->raiseLintAtOffset(
            $value->getOffset(),
            pht('Multi-lined arrays should have trailing commas.'),
            $original,
            $replacement);
        } else if ($value->getLineNumber() == $array->getEndLineNumber()) {
          $close = last($array->getTokens());

          $this->raiseLintAtToken(
            $close,
            pht('Closing parenthesis should be on a new line.'),
            "\n".$array->getIndentation().$close->getValue());
        }
      } else if ($after && $after->getValue() == ',') {
        $this->raiseLintAtToken(
          $after,
          pht('Single lined arrays should not have a trailing comma.'),
          '');
      }
    }
  }

}
