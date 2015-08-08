<?php

final class ArcanistObjectOperatorSpacingXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 74;

  public function getLintName() {
    return pht('Object Operator Spacing');
  }

  public function getLintSeverity() {
    return ArcanistLintSeverity::SEVERITY_WARNING;
  }

  public function process(XHPASTNode $root) {
    $operators = $root->selectTokensOfType('T_OBJECT_OPERATOR');

    foreach ($operators as $operator) {
      $before = $operator->getNonsemanticTokensBefore();
      $after  = $operator->getNonsemanticTokensAfter();

      if ($before) {
        $value = implode('', mpull($before, 'getValue'));

        if (strpos($value, "\n") !== false) {
          continue;
        }

        $this->raiseLintAtOffset(
          head($before)->getOffset(),
          pht('There should be no whitespace before the object operator.'),
          $value,
          '');
      }

      if ($after) {
        $this->raiseLintAtOffset(
          head($after)->getOffset(),
          pht('There should be no whitespace after the object operator.'),
          implode('', mpull($before, 'getValue')),
          '');
      }
    }
  }

}
