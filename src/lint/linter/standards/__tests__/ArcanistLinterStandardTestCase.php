<?php

final class ArcanistLinterStandardTestCase extends PhutilTestCase {

  public function testLoadAllStandards() {
    ArcanistLinterStandard::loadAllStandards();
    $this->assertTrue(true);
  }

}
