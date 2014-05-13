<?php

final class ArcanistXHPASTLinterTestCase
  extends ArcanistArcanistLinterTestCase {

  public function testXHPASTLint() {
    $linter = new ArcanistXHPASTLinter();

    $linter->setCustomSeverityMap(
      array(
        ArcanistXHPASTLinter::LINT_RAGGED_CLASSTREE_EDGE
          => ArcanistLintSeverity::SEVERITY_WARNING,
      ));

    $this->executeTestsInDirectory(
      dirname(__FILE__).'/xhpast/',
      $linter);
  }

}
