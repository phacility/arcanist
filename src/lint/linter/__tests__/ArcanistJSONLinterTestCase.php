<?php

final class ArcanistJSONLinterTestCase extends ArcanistArcanistLinterTestCase {

  public function testJSONLinter() {
    $this->executeTestsInDirectory(
      dirname(__FILE__).'/jsonlint/',
      new ArcanistJSONLinter());
  }

}
