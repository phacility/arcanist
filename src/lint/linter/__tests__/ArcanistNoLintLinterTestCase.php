<?php

final class ArcanistNoLintLinterTestCase extends ArcanistLinterTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(dirname(__FILE__).'/nolint/');
  }

}
