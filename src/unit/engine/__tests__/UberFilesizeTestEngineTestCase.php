<?php

/**
* Tests for @{class:UberFilesizeTestEngine}.
*/
final class UberFilesizeTestEngineTestCase extends PhutilTestCase {

  public function testRuntimeConfig() {
    $filesize_test_engine = new UberFilesizeTestEngine();

    $configuration_manager = new ArcanistConfigurationManager();
    $filesize_test_engine->setConfigurationManager($configuration_manager);

    // no key
    $configuration_manager->setRuntimeConfig("key1", "value");
    $this->assertException(
      ArcanistUsageException::class, array($filesize_test_engine, 'run'));

    // invalid value
    $configuration_manager->setRuntimeConfig(
      UberFilesizeTestEngine::MAX_FILESIZE_LIMIT_KEY, 'value');
    $this->assertException(
      ArcanistUsageException::class, array($filesize_test_engine, 'run'));

    // invalid value
    $configuration_manager->setRuntimeConfig(
      UberFilesizeTestEngine::MAX_FILESIZE_LIMIT_KEY, '100a');
    $this->assertException(
      ArcanistUsageException::class, array($filesize_test_engine, 'run'));

    // non-integer value
    $configuration_manager->setRuntimeConfig(
      UberFilesizeTestEngine::MAX_FILESIZE_LIMIT_KEY, '100');
    $this->assertException(
      ArcanistUsageException::class, array($filesize_test_engine, 'run'));

    // correct value, should pass
    $size = 9999999;
    $configuration_manager->setRuntimeConfig(
      UberFilesizeTestEngine::MAX_FILESIZE_LIMIT_KEY, $size);
    $result = $filesize_test_engine->run();
    $this->assertEqual(count($result), 1);

    $this->assertEqual($result[0]->getResult(),
      ArcanistUnitTestResult::RESULT_PASS);
    $this->assertEqual($result[0]->getName(),
      "All modified files are smaller than the limit ({$size}).");
  }
}
