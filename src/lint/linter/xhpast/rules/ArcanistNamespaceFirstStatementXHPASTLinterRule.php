<?php

final class ArcanistNamespaceFirstStatementXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 98;

  public function getLintName() {
    return pht('`%s` Statement Must Be The First Statement', 'namespace');
  }

  public function process(XHPASTNode $root) {
    $namespaces = $root->selectDescendantsOfType('n_NAMESPACE');

    if (!count($namespaces)) {
      return;
    }

    $statements = $root->getChildOfType(0, 'n_STATEMENT_LIST');

    // Ignore the first statement, which should be `n_OPEN_TAG`.
    $second_statement = $statements->getChildByIndex(1)->getChildByIndex(0);

    if ($second_statement->getTypeName() != 'n_NAMESPACE') {
      $this->raiseLintAtNode(
        $second_statement,
        pht(
          'A script which contains a `%s` statement expects the very first '.
          'statement to be a `%s` statement. Otherwise, a PHP fatal error '.
          'will occur. %s',
          'namespace',
          'namespace', $second_statement->getTypeName()));
    }
  }

}
