<?php

final class ArcanistJSONLintLinterTestCase
  extends ArcanistArcanistLinterTestCase {

  public function testJSONLintLinter() {
    $this->executeTestsInDirectory(
      dirname(__FILE__).'/jsonlint/',
      new ArcanistJSONLintLinter());
  }

}
