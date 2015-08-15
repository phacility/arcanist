<?php

final class ArcanistParseStrUseXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 80;

  public function getLintName() {
    return pht('Questionable Use of %s', 'parse_str()');
  }

  public function process(XHPASTNode $root) {
    $calls = $this->getFunctionCalls($root, array('parse_str'));

    foreach ($calls as $call) {
      $call_params = $call->getChildOfType(1, 'n_CALL_PARAMETER_LIST');

      if (count($call_params->getChildren()) < 2) {
        $this->raiseLintAtNode(
          $call,
          pht(
            'Avoid %s unless the second parameter is specified. '.
            'It is confusing and hinders static analysis.',
            'parse_str()'));
      }
    }
  }

}
