<?php

final class ArcanistHexadecimalNumericScalarCasingXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 127;

  public function getLintName() {
    return pht('Hexadecimal Integer Casing');
  }

  public function getLintSeverity() {
    return ArcanistLintSeverity::SEVERITY_WARNING;
  }

  public function process(XHPASTNode $root) {
    $hexadecimals = $this->getHexadecimalNumericScalars($root);

    foreach ($hexadecimals as $hexadecimal) {
      $value = substr($hexadecimal->getConcreteString(), 2);

      if (!preg_match('/^0x[0-9A-F]+$/', $hexadecimal->getConcreteString())) {
        $this->raiseLintAtNode(
          $hexadecimal,
          pht(
            'For consistency, write hexadecimals integers '.
            'in uppercase with a leading `%s`.',
            '0x'),
          '0x'.strtoupper($value));
      }
    }
  }

  private function getHexadecimalNumericScalars(XHPASTNode $root) {
    $numeric_scalars = $root->selectDescendantsOfType('n_NUMERIC_SCALAR');
    $hexadecimal_numeric_scalars = array();

    foreach ($numeric_scalars as $numeric_scalar) {
      $number = $numeric_scalar->getConcreteString();

      if (preg_match('/^0x[0-9A-F]+$/i', $number)) {
        $hexadecimal_numeric_scalars[] = $numeric_scalar;
      }
    }

    return $hexadecimal_numeric_scalars;

  }

}
