<?php

final class ArcanistInvalidDefaultParameterXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 70;

  public function getLintName() {
    return pht('Invalid Default Parameter');
  }

  public function process(XHPASTNode $root) {
    $parameters = $root->selectDescendantsOfType('n_DECLARATION_PARAMETER');

    foreach ($parameters as $parameter) {
      $type = $parameter->getChildByIndex(0);
      $default = $parameter->getChildByIndex(2);

      if ($type->getTypeName() == 'n_EMPTY') {
        continue;
      }

      if ($default->getTypeName() == 'n_EMPTY') {
        continue;
      }

      $default_is_null = $default->getTypeName() == 'n_SYMBOL_NAME' &&
        strtolower($default->getConcreteString()) == 'null';

      switch (strtolower($type->getConcreteString())) {
        case 'array':
          if ($default->getTypeName() == 'n_ARRAY_LITERAL') {
            break;
          }
          if ($default_is_null) {
            break;
          }

          $this->raiseLintAtNode(
            $default,
            pht(
              'Default value for parameters with %s type hint '.
              'can only be an %s or %s.',
              'array',
              'array',
              'null'));
          break;

        case 'callable':
          if ($default_is_null) {
            break;
          }

          $this->raiseLintAtNode(
            $default,
            pht(
              'Default value for parameters with %s type hint can only be %s.',
              'callable',
              'null'));
          break;

        default:
          // Class/interface parameter.
          if ($default_is_null) {
            break;
          }

          $this->raiseLintAtNode(
            $default,
            pht(
              'Default value for parameters with a class type hint '.
              'can only be %s.',
              'null'));
          break;
      }
    }
  }

}
