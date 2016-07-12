<?php

/**
 * Exit is parsed as an expression, but using it as such is almost always
 * wrong. That is, this is valid:
 *
 *   strtoupper(33 * exit - 6);
 *
 * When exit is used as an expression, it causes the program to terminate with
 * exit code 0. This is likely not what is intended; these statements have
 * different effects:
 *
 *   exit(-1);
 *   exit -1;
 *
 * The former exits with a failure code, the latter with a success code!
 */
final class ArcanistExitExpressionXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 17;

  public function getLintName() {
    return pht('`%s` Used as Expression', 'exit');
  }

  public function process(XHPASTNode $root) {
    $unaries = $root->selectDescendantsOfType('n_UNARY_PREFIX_EXPRESSION');

    foreach ($unaries as $unary) {
      $operator = $unary->getChildByIndex(0)->getConcreteString();

      if (strtolower($operator) === 'exit') {
        if ($unary->getParentNode()->getTypeName() !== 'n_STATEMENT') {
          $this->raiseLintAtNode(
            $unary,
            pht(
              'Use `%s` as a statement, not an expression.',
              'exit'));
        }
      }
    }
  }

}
