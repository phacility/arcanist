<?php

final class ArcanistDuplicateSwitchCaseXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 50;

  public function getLintName() {
    return pht('Duplicate Case Statements');
  }

  public function process(XHPASTNode $root) {
    $switch_statements = $root->selectDescendantsOfType('n_SWITCH');

    foreach ($switch_statements as $switch_statement) {
      $case_statements = $switch_statement
        ->getChildOfType(1, 'n_STATEMENT_LIST')
        ->getChildrenOfType('n_CASE');
      $nodes_by_case = array();

      foreach ($case_statements as $case_statement) {
        $case = $case_statement
          ->getChildByIndex(0)
          ->getSemanticString();
        $nodes_by_case[$case][] = $case_statement;
      }

      foreach ($nodes_by_case as $case => $nodes) {
        if (count($nodes) <= 1) {
          continue;
        }

        $node = array_pop($nodes_by_case[$case]);
        $message = $this->raiseLintAtNode(
          $node,
          pht(
            'Duplicate case in switch statement. PHP will ignore all '.
            'but the first case.'));

        $locations = array();
        foreach ($nodes_by_case[$case] as $node) {
          $locations[] = $this->getOtherLocation($node->getOffset());
        }
        $message->setOtherLocations($locations);
      }
    }
  }

}
