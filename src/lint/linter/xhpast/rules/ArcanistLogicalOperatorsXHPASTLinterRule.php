<?php

final class ArcanistLogicalOperatorsXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 58;

  public function getLintName() {
    return pht('Logical Operators');
  }

  public function getLintSeverity() {
    return ArcanistLintSeverity::SEVERITY_ADVICE;
  }

  public function process(XHPASTNode $root) {
    $logical_ands = $root->selectTokensOfType('T_LOGICAL_AND');
    $logical_ors  = $root->selectTokensOfType('T_LOGICAL_OR');

    foreach ($logical_ands as $logical_and) {
      $this->raiseLintAtToken(
        $logical_and,
        pht('Use `%s` instead of `%s`.', '&&', 'and'),
        '&&');
    }

    foreach ($logical_ors as $logical_or) {
      $this->raiseLintAtToken(
        $logical_or,
        pht('Use `%s` instead of `%s`.', '||', 'or'),
        '||');
    }
  }

}
