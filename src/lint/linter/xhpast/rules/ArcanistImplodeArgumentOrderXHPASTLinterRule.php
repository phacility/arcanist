<?php

final class ArcanistImplodeArgumentOrderXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 129;

  public function getLintName() {
    return pht('Implode With Glue First');
  }

  public function getLintSeverity() {
    return ArcanistLintSeverity::SEVERITY_ERROR;
  }

  public function process(XHPASTNode $root) {
    $implosions = $this->getFunctionCalls($root, array('implode'));
    foreach ($implosions as $implosion) {
      $parameters = $implosion->getChildOfType(1, 'n_CALL_PARAMETER_LIST');

      if (count($parameters->getChildren()) != 2) {
        continue;
      }

      $parameter = $parameters->getChildByIndex(1);

      // If the value is a static scalar, like a string literal, it's probably
      // the glue.
      $is_scalar = $parameter->isStaticScalar();

      // If the value is a constant, like "DIRECTORY_SEPARATOR", it's probably
      // the glue.
      $is_constant = ($parameter->getTypeName() === 'n_SYMBOL_NAME');

      $looks_like_glue = ($is_scalar || $is_constant);
      if (!$looks_like_glue) {
        continue;
      }

      $this->raiseLintAtNode(
        $implosion,
        pht(
          'When calling "implode()", pass the "glue" argument first. (The '.
          'other parameter order is deprecated in PHP 7.4 and raises a '.
          'warning.)'));
    }
  }

}
