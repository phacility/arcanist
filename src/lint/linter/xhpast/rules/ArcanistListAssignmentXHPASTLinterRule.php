<?php

final class ArcanistListAssignmentXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 77;

  public function getLintName() {
    return pht('List Assignment');
  }

  public function getLintSeverity() {
    return ArcanistLintSeverity::SEVERITY_WARNING;
  }

  public function process(XHPASTNode $root) {
    $assignment_lists = $root->selectDescendantsOfType('n_ASSIGNMENT_LIST');

    foreach ($assignment_lists as $assignment_list) {
      $tokens = array_slice($assignment_list->getTokens(), 1, -1);

      foreach (array_reverse($tokens) as $token) {
        if ($token->getTypeName() == ',') {
          $this->raiseLintAtToken(
            $token,
            pht('Unnecessary comma in list assignment.'),
            '');
          continue;
        }

        if ($token->isSemantic()) {
          break;
        }
      }
    }
  }

}
