<?php

final class ArcanistJscsLinterTestCase extends ArcanistArcanistLinterTestCase {

  public function testJscsLinter() {
    $this->executeTestsInDirectory(
      dirname(__FILE__).'/jscs/',
      new ArcanistJscsLinter());
  }

}
