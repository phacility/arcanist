<?php

final class ArcanistUnableToParseXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 2;

  public function getLintName() {
    return pht('Unable to Parse');
  }

  public function getLintSeverity() {
    return ArcanistLintSeverity::SEVERITY_WARNING;
  }

  public function process(XHPASTNode $root) {
    // This linter rule isn't used explicitly.
  }

}
