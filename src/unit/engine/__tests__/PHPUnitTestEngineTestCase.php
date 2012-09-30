<?php

/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * Tests for @{class:PHPUnitTestEngine}.
 *
 * @group testcase
 */
final class PHPUnitTestEngineTestCase extends ArcanistTestCase {

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
