<?php

final class ArcanistJSHintLinterTestCase
  extends ArcanistArcanistLinterTestCase {

  public function testJSHintLinter() {
    $this->executeTestsInDirectory(dirname(__FILE__).'/jshint/');
  }

}
