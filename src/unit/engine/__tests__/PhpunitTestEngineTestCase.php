<?php

/**
 * Tests for @{class:PhpunitTestEngine}.
 */
final class PhpunitTestEngineTestCase extends PhutilTestCase {

  public function testSearchLocations() {
    $path = '/path/to/some/file/X.php';

    $this->assertEqual(
      array(
        '/path/to/some/file/',
        '/path/to/some/file/tests/',
        '/path/to/some/file/Tests/',
        '/path/to/some/tests/',
        '/path/to/some/Tests/',
        '/path/to/tests/',
        '/path/to/Tests/',
        '/path/tests/',
        '/path/Tests/',
        '/tests/',
        '/Tests/',
        '/path/to/tests/file/',
        '/path/to/Tests/file/',
        '/path/tests/some/file/',
        '/path/Tests/some/file/',
        '/tests/to/some/file/',
        '/Tests/to/some/file/',
        '/path/to/some/tests/file/',
        '/path/to/some/Tests/file/',
        '/path/to/tests/some/file/',
        '/path/to/Tests/some/file/',
        '/path/tests/to/some/file/',
        '/path/Tests/to/some/file/',
        '/tests/path/to/some/file/',
        '/Tests/path/to/some/file/',
      ),
      PhpunitTestEngine::getSearchLocationsForTests($path));
  }

}
