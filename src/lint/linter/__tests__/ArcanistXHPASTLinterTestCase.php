<?php

/**
 * Tests for @{class:ArcanistXHPASTLinter}.
 *
 * @group testcase
 */
final class ArcanistXHPASTLinterTestCase
  extends ArcanistArcanistLinterTestCase {

  public function testXHPASTLint() {
    $linter = new ArcanistXHPASTLinter();

    $linter->setCustomSeverityMap(
      array(
        ArcanistXHPASTLinter::LINT_RAGGED_CLASSTREE_EDGE
          => ArcanistLintSeverity::SEVERITY_WARNING,
      ));

    return $this->executeTestsInDirectory(
      dirname(__FILE__).'/xhpast/',
      $linter);
  }

}
