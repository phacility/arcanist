<?php

final class ArcanistDynamicDefineXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 12;

  public function getLintName() {
    return pht('Dynamic %s', 'define()');
  }

  public function process(XHPASTNode $root) {
    $calls = $this->getFunctionCalls($root, array('define'));

    foreach ($calls as $call) {
      $parameter_list = $call->getChildOfType(1, 'n_CALL_PARAMETER_LIST');
      $defined = $parameter_list->getChildByIndex(0);

      if (!$defined->isStaticScalar()) {
        $this->raiseLintAtNode(
          $defined,
          pht(
            'First argument to %s must be a string literal.',
            'define()'));
      }
    }
  }

}
