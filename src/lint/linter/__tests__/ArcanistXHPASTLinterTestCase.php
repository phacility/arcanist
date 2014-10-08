<?php

final class ArcanistXHPASTLinterTestCase
  extends ArcanistArcanistLinterTestCase {

  public function testXHPASTLint() {
    $this->executeTestsInDirectory(
      dirname(__FILE__).'/xhpast/',
      new ArcanistXHPASTLinter());
  }

}
