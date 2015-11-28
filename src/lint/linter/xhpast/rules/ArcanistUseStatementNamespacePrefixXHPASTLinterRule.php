<?php

final class ArcanistUseStatementNamespacePrefixXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 97;

  public function getLintName() {
    return pht('`%s` Statement Namespace Prefix', 'use');
  }

  public function getLintSeverity() {
    return ArcanistLintSeverity::SEVERITY_WARNING;
  }

  public function process(XHPASTNode $root) {
    $use_lists = $root->selectDescendantsOfType('n_USE_LIST');

    foreach ($use_lists as $use_list) {
      $uses = $use_list->selectDescendantsOfType('n_USE');

      foreach ($uses as $use) {
        $symbol = $use->getChildOfType(0, 'n_SYMBOL_NAME');
        $symbol_name = $symbol->getConcreteString();

        if ($symbol_name[0] == '\\') {
          $this->raiseLintAtNode(
            $symbol,
            pht(
              'Imported symbols should not be prefixed with `%s`.',
              '\\'),
            substr($symbol_name, 1));
        }
      }
    }
  }

}
