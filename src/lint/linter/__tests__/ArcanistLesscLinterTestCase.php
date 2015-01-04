<?php

final class ArcanistLesscLinterTestCase extends ArcanistArcanistLinterTestCase {

  public function testLesscLinter() {
    $this->executeTestsInDirectory(dirname(__FILE__).'/lessc/');
  }

}
