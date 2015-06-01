<?php

final class ArcanistSyntaxErrorXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 1;

  public function getLintName() {
    return pht('PHP Syntax Error!');
  }

  public function process(XHPASTNode $root) {
    // This linter rule isn't used explicitly.
  }

}
