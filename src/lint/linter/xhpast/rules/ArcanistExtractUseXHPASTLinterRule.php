<?php

final class ArcanistExtractUseXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 4;

  public function getLintName() {
    return pht('Use of %s', 'extract()');
  }

  public function process(XHPASTNode $root) {
    $calls = $this->getFunctionCalls($root, array('extract'));

    foreach ($calls as $call) {
      $this->raiseLintAtNode(
        $call,
        pht(
          'Avoid %s. It is confusing and hinders static analysis.',
          'extract()'));
    }
  }

}
