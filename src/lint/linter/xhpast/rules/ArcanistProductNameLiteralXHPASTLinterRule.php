<?php

final class ArcanistProductNameLiteralXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 134;

  public function getLintName() {
    return pht('Use of Product Name Literal');
  }

  public function getLintSeverity() {
    return ArcanistLintSeverity::SEVERITY_WARNING;
  }

  public function process(XHPASTNode $root) {
    $calls = $root->selectDescendantsOfType('n_FUNCTION_CALL');

    $product_names = PlatformSymbols::getProductNames();
    foreach ($product_names as $k => $product_name) {
      $product_names[$k] = preg_quote($product_name);
    }

    $search_pattern = '(\b(?:'.implode('|', $product_names).')\b)i';

    foreach ($calls as $call) {
      $name = $call->getChildByIndex(0)->getConcreteString();

      if ($name !== 'pht') {
        continue;
      }

      $parameters = $call->getChildByIndex(1);

      if (!$parameters->getChildren()) {
        continue;
      }

      $identifier = $parameters->getChildByIndex(0);
      if (!$identifier->isConstantString()) {
        continue;
      }

      $literal_value = $identifier->evalStatic();

      $matches = phutil_preg_match_all($search_pattern, $literal_value);
      if (!$matches[0]) {
        continue;
      }

      $name_list = array();
      foreach ($matches[0] as $match) {
        $name_list[phutil_utf8_strtolower($match)] = $match;
      }
      $name_list = implode(', ', $name_list);

      $this->raiseLintAtNode(
        $identifier,
        pht(
          'Avoid use of product name literals in "pht()": use generic '.
          'language or an appropriate method from the "PlatformSymbols" class '.
          'instead so the software can be forked. String uses names: %s.',
          $name_list));
    }
  }

}
