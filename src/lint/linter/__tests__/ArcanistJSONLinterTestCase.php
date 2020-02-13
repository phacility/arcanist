<?php

final class ArcanistJSONLinterTestCase extends ArcanistLinterTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(dirname(__FILE__).'/json/');
  }

}
