<?php

/**
 * Test cases were mostly taken from
 * https://git.gnome.org/browse/libxml2/tree/test.
 */
final class ArcanistXMLLinterTestCase extends ArcanistArcanistLinterTestCase {
  public function testLintPath() {
    $this->executeTestsInDirectory(
      dirname(__FILE__).'/xml/',
      new ArcanistXMLLinter());
  }
}
