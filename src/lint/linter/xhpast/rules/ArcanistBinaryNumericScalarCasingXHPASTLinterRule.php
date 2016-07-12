<?php

final class ArcanistBinaryNumericScalarCasingXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 131;

  public function getLintName() {
    return pht('Binary Integer Casing');
  }

  public function getLintSeverity() {
    return ArcanistLintSeverity::SEVERITY_WARNING;
  }

  public function process(XHPASTNode $root) {
    $binaries = $this->getBinaryNumericScalars($root);

    foreach ($binaries as $binary) {
      $value = substr($binary->getConcreteString(), 2);

      if (!preg_match('/^0b[01]+$/', $binary->getConcreteString())) {
        $this->raiseLintAtNode(
          $binary,
          pht(
            'For consistency, write binary integers with a leading `%s`.',
            '0b'),
          '0b'.$value);
      }
    }
  }

  private function getBinaryNumericScalars(XHPASTNode $root) {
    $numeric_scalars = $root->selectDescendantsOfType('n_NUMERIC_SCALAR');
    $binary_numeric_scalars = array();

    foreach ($numeric_scalars as $numeric_scalar) {
      $number = $numeric_scalar->getConcreteString();

      if (preg_match('/^0b[01]+$/i', $number)) {
        $binary_numeric_scalars[] = $numeric_scalar;
      }
    }

    return $binary_numeric_scalars;

  }

}
