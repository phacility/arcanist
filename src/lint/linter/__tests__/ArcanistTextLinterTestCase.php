<?php

final class ArcanistTextLinterTestCase extends ArcanistArcanistLinterTestCase {

  public function testTextLint() {
    $this->executeTestsInDirectory(
      dirname(__FILE__).'/text/',
      new ArcanistTextLinter());
  }

}
