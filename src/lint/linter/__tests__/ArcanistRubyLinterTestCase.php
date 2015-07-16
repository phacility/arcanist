<?php

final class ArcanistRubyLinterTestCase extends ArcanistExternalLinterTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(dirname(__FILE__).'/ruby/');
  }

}
