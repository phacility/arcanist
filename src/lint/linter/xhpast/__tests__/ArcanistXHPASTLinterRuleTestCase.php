<?php

final class ArcanistXHPASTLinterRuleTestCase extends PhutilTestCase {

  public function testLoadAllRules() {
    ArcanistXHPASTLinterRule::loadAllRules();
    $this->assertTrue(true);
  }

}
