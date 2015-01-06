<?php

final class ArcanistPhutilXHPASTLinterTestCase
  extends ArcanistArcanistLinterTestCase {

  public function testLinter() {
    $linter = new ArcanistPhutilXHPASTLinter();
    $linter->setDeprecatedFunctions(array(
      'deprecated_function' => 'This function is most likely deprecated.',
    ));

    $this->executeTestsInDirectory(dirname(__FILE__).'/phlxhp/', $linter);
  }

}
