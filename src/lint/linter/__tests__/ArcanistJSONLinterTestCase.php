<?php

final class ArcanistJSONLinterTestCase extends ArcanistArcanistLinterTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(dirname(__FILE__).'/jsonlint/');
  }

}
