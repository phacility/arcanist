<?php

final class ArcanistGoFmtLinterTestCase extends ArcanistExternalLinterTestCase {
  public function testLinter() {
    $this->executeTestsInDirectory(dirname(__FILE__).'/gofmt/');
  }
}
