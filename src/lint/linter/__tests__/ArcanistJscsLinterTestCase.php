<?php

final class ArcanistJscsLinterTestCase extends ArcanistArcanistLinterTestCase {

  public function testJscsLinter() {
    // NOTE: JSCS will only lint files with a `*.js` extension.
    //
    // See https://github.com/mdevils/node-jscs/issues/444
    $this->assertTrue(true);
    return;

    $this->executeTestsInDirectory(
      dirname(__FILE__).'/jscs/',
      new ArcanistJscsLinter());
  }

}
