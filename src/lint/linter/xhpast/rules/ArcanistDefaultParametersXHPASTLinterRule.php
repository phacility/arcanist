<?php

final class ArcanistDefaultParametersXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 60;

  public function getLintName() {
    return pht('Default Parameters');
  }

  public function getLintSeverity() {
    return ArcanistLintSeverity::SEVERITY_WARNING;
  }

  public function process(XHPASTNode $root) {
    $parameter_lists = $root->selectDescendantsOfType(
      'n_DECLARATION_PARAMETER_LIST');

    foreach ($parameter_lists as $parameter_list) {
      $default_found = false;
      $parameters    = $parameter_list->selectDescendantsOfType(
        'n_DECLARATION_PARAMETER');

      foreach ($parameters as $parameter) {
        $default_value = $parameter->getChildByIndex(2);

        if ($default_value->getTypeName() != 'n_EMPTY') {
          $default_found = true;
        } else if ($default_found) {
          $this->raiseLintAtNode(
            $parameter_list,
            pht(
              'Arguments with default values must be at the end '.
              'of the argument list.'));
        }
      }
    }
  }

}
