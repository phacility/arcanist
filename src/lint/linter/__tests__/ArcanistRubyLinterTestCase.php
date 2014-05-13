<?php

final class ArcanistRubyLinterTestCase extends ArcanistArcanistLinterTestCase {

  public function testRubyLint() {
    $this->executeTestsInDirectory(
      dirname(__FILE__).'/ruby/',
      new ArcanistRubyLinter());
  }

}
