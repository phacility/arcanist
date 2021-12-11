<?php

final class ArcanistEachUseXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 133;

  public function getLintName() {
    return pht('Use of Removed Function "each()"');
  }

  public function process(XHPASTNode $root) {
    $calls = $this->getFunctionCalls($root, array('each'));

    foreach ($calls as $call) {
      $this->raiseLintAtNode(
        $call,
        pht(
          'Do not use "each()". This function was deprecated in PHP 7.2 '.
          'and removed in PHP 8.0'));
    }
  }

}
