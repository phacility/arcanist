<?php

final class ArcanistGoLintLinterTestCase
  extends ArcanistArcanistLinterTestCase {

  public function testGoLintLinter() {
    $this->executeTestsInDirectory(
      dirname(__FILE__).'/golint/',
      new ArcanistGoLintLinter());
  }

}
