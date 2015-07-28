<?php

final class ArcanistSlownessXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 36;

  public function getLintName() {
    return pht('Slow Construct');
  }

  public function getLintSeverity() {
    return ArcanistLintSeverity::SEVERITY_WARNING;
  }

  public function process(XHPASTNode $root) {
    $this->lintStrstrUsedForCheck($root);
    $this->lintStrposUsedForStart($root);
  }

  private function lintStrstrUsedForCheck(XHPASTNode $root) {
    $expressions = $root->selectDescendantsOfType('n_BINARY_EXPRESSION');

    foreach ($expressions as $expression) {
      $operator = $expression->getChildOfType(1, 'n_OPERATOR');
      $operator = $operator->getConcreteString();

      if ($operator !== '===' && $operator !== '!==') {
        continue;
      }

      $false = $expression->getChildByIndex(0);
      if ($false->getTypeName() === 'n_SYMBOL_NAME' &&
          $false->getConcreteString() === 'false') {
        $strstr = $expression->getChildByIndex(2);
      } else {
        $strstr = $false;
        $false = $expression->getChildByIndex(2);
        if ($false->getTypeName() !== 'n_SYMBOL_NAME' ||
            $false->getConcreteString() !== 'false') {
          continue;
        }
      }

      if ($strstr->getTypeName() !== 'n_FUNCTION_CALL') {
        continue;
      }

      $name = strtolower($strstr->getChildByIndex(0)->getConcreteString());
      if ($name === 'strstr' || $name === 'strchr') {
        $this->raiseLintAtNode(
          $strstr,
          pht(
            'Use %s for checking if the string contains something.',
            'strpos()'));
      } else if ($name === 'stristr') {
        $this->raiseLintAtNode(
          $strstr,
          pht(
            'Use %s for checking if the string contains something.',
            'stripos()'));
      }
    }
  }

  private function lintStrposUsedForStart(XHPASTNode $root) {
    $expressions = $root->selectDescendantsOfType('n_BINARY_EXPRESSION');

    foreach ($expressions as $expression) {
      $operator = $expression->getChildOfType(1, 'n_OPERATOR');
      $operator = $operator->getConcreteString();

      if ($operator !== '===' && $operator !== '!==') {
        continue;
      }

      $zero = $expression->getChildByIndex(0);
      if ($zero->getTypeName() === 'n_NUMERIC_SCALAR' &&
          $zero->getConcreteString() === '0') {
        $strpos = $expression->getChildByIndex(2);
      } else {
        $strpos = $zero;
        $zero = $expression->getChildByIndex(2);
        if ($zero->getTypeName() !== 'n_NUMERIC_SCALAR' ||
            $zero->getConcreteString() !== '0') {
          continue;
        }
      }

      if ($strpos->getTypeName() !== 'n_FUNCTION_CALL') {
        continue;
      }

      $name = strtolower($strpos->getChildByIndex(0)->getConcreteString());
      if ($name === 'strpos') {
        $this->raiseLintAtNode(
          $strpos,
          pht(
            'Use %s for checking if the string starts with something.',
            'strncmp()'));
      } else if ($name === 'stripos') {
        $this->raiseLintAtNode(
          $strpos,
          pht(
            'Use %s for checking if the string starts with something.',
            'strncasecmp()'));
      }
    }
  }

}
