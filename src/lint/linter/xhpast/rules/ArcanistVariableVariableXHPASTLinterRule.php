<?php

final class ArcanistVariableVariableXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 3;

  public function getLintName() {
    return pht('Use of Variable Variable');
  }

  public function process(XHPASTNode $root) {
    $vvars = $root->selectDescendantsOfType('n_VARIABLE_VARIABLE');

    foreach ($vvars as $vvar) {
      $this->raiseLintAtNode(
        $vvar,
        pht(
          'Rewrite this code to use an array. Variable variables are unclear '.
          'and hinder static analysis.'));
    }
  }

}
