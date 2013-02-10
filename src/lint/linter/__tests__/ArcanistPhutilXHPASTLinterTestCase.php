<?php

/**
 * @group testcase
 */
final class ArcanistPhutilXHPASTLinterTestCase
  extends ArcanistArcanistLinterTestCase {

  public function testPhutilXHPASTLint() {
    $linter = new ArcanistPhutilXHPASTLinter();
    $linter->setXHPASTLinter(new ArcanistXHPASTLinter());
    $linter->setDeprecatedFunctions(array(
      'deprecated_function' => 'This function is most likely deprecated.',
    ));

    $working_copy = ArcanistWorkingCopyIdentity::newFromPath(__FILE__);
    return $this->executeTestsInDirectory(
      dirname(__FILE__).'/phlxhp/',
      $linter,
      $working_copy);
  }

}
