<?php

final class ArcanistUnnecessarySemicolonXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 56;

  public function getLintName() {
    return pht('Unnecessary Semicolon');
  }

  public function getLintSeverity() {
    return ArcanistLintSeverity::SEVERITY_ADVICE;
  }

  public function process(XHPASTNode $root) {
    $statements = $root->selectDescendantsOfType('n_STATEMENT');

    foreach ($statements as $statement) {
      if ($statement->getParentNode()->getTypeName() == 'n_DECLARE') {
        continue;
      }

      if (count($statement->getChildren()) > 1) {
        continue;
      } else if ($statement->getChildByIndex(0)->getTypeName() != 'n_EMPTY') {
        continue;
      }

      if ($statement->getConcreteString() == ';') {
        $this->raiseLintAtNode(
          $statement,
          pht('Unnecessary semicolons after statement.'),
          '');
      }
    }
  }

}
