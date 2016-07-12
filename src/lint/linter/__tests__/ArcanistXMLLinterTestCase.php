<?php

/**
 * Test cases were mostly taken from
 * https://git.gnome.org/browse/libxml2/tree/test.
 */
final class ArcanistXMLLinterTestCase extends ArcanistLinterTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(dirname(__FILE__).'/xml/');
  }

}
