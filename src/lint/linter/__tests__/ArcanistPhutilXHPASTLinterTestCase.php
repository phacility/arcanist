<?php

final class ArcanistPhutilXHPASTLinterTestCase extends ArcanistLinterTestCase {

  public function testLinter() {
    $linter = new ArcanistPhutilXHPASTLinter();
    $linter->setDeprecatedFunctions(array(
      'deprecated_function' => pht('This function is most likely deprecated.'),
    ));

    $this->executeTestsInDirectory(dirname(__FILE__).'/phlxhp/', $linter);
  }

}
