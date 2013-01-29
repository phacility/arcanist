<?php

/**
 * @group testcase
 */
final class ArcanistPhutilXHPASTLinterTestCase
  extends ArcanistArcanistLinterTestCase {

  public function testPhutilXHPASTLint() {
    $linter = new ArcanistPhutilXHPASTLinter();
    $linter->setXHPASTLinter(new ArcanistXHPASTLinter());

    $working_copy = ArcanistWorkingCopyIdentity::newFromPath(__FILE__);
    return $this->executeTestsInDirectory(
      dirname(__FILE__).'/phlxhp/',
      $linter,
      $working_copy);
  }

}
