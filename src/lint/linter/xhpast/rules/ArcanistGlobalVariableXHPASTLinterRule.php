<?php

final class ArcanistGlobalVariableXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 79;

  public function getLintName() {
    return pht('Global Variables');
  }

  public function getLintSeverity() {
    return ArcanistLintSeverity::SEVERITY_WARNING;
  }

  public function process(XHPASTNode $root) {
    $nodes = $root->selectDescendantsOfType('n_GLOBAL_DECLARATION_LIST');

    foreach ($nodes as $node) {
      $this->raiseLintAtNode(
        $node,
        pht(
          'Limit the use of global variables. Global variables are '.
          'generally a bad idea and should be avoided when possible.'));
    }
  }

}
