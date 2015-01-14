<?php

final class ArcanistNoLintLinterTestCase
  extends ArcanistArcanistLinterTestCase {

  public function testLinter() {
    return $this->executeTestsInDirectory(dirname(__FILE__).'/nolint/');
  }

}
