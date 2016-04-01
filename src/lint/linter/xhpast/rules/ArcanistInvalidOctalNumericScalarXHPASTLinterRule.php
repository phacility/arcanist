<?php

final class ArcanistInvalidOctalNumericScalarXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 125;

  public function getLintName() {
    return pht('Invalid Octal Numeric Scalar');
  }

  public function process(XHPASTNode $root) {
    $octals = $this->getOctalNumericScalars($root);

    foreach ($octals as $octal) {
      if (!preg_match('/^0[0-7]*$/', $octal->getConcreteString())) {
        $this->raiseLintAtNode(
          $octal,
          pht(
            'Invalid octal numeric scalar. `%s` is not a '.
            'valid octal and will be interpreted as `%d`.',
            $octal->getConcreteString(),
            intval($octal->getConcreteString(), 8)));
      }
    }
  }

  private function getOctalNumericScalars(XHPASTNode $root) {
    $numeric_scalars = $root->selectDescendantsOfType('n_NUMERIC_SCALAR');
    $octal_numeric_scalars = array();

    foreach ($numeric_scalars as $numeric_scalar) {
      $number = $numeric_scalar->getConcreteString();

      if (preg_match('/^0\d+$/', $number)) {
        $octal_numeric_scalars[] = $numeric_scalar;
      }
    }

    return $octal_numeric_scalars;

  }

}
