<?php

final class ArcanistArrayCombineXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 84;

  public function getLintName() {
    return pht('`%s` Unreliable', 'array_combine()');
  }

  public function getLintSeverity() {
    return ArcanistLintSeverity::SEVERITY_DISABLED;
  }

  public function process(XHPASTNode $root) {
    $function_calls = $this->getFunctionCalls($root, array('array_combine'));

    foreach ($function_calls as $call) {
      $name = $call->getChildByIndex(0)->getConcreteString();
      $parameter_list = $call->getChildOfType(1, 'n_CALL_PARAMETER_LIST');

      if (count($parameter_list->getChildren()) !== 2) {
        // Wrong number of parameters, but raise that elsewhere if we want.
        continue;
      }

      $first  = $parameter_list->getChildByIndex(0);
      $second = $parameter_list->getChildByIndex(1);

      if ($first->getConcreteString() == $second->getConcreteString()) {
        $this->raiseLintAtNode(
          $call,
          pht(
            'Prior to PHP 5.4, `%s` fails when given empty arrays. '.
            'Prefer to write `%s` as `%s`.',
            'array_combine()',
            'array_combine($x, $x)',
            'array_fuse($x)'));
      }
    }
  }

}
