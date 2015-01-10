<?php

abstract class ArcanistExternalLinterTestCase extends ArcanistLinterTestCase {

  public final function testVersion() {
    try {
      $version = $this->getLinter()->getVersion();
      $this->assertTrue(
        $version !== false,
        pht('Failed to parse version from command.'));
    } catch (ArcanistMissingLinterException $ex) {
      $this->assertSkipped($ex->getMessage());
    }
  }

}
