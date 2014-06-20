<?php

/**
 * Facilitates implementation of test cases for @{class:ArcanistLinter}s.
 */
abstract class ArcanistLinterTestCase extends ArcanistPhutilTestCase {

  public function executeTestsInDirectory($root, ArcanistLinter $linter) {
    $files = id(new FileFinder($root))
      ->withType('f')
      ->withSuffix('lint-test')
      ->find();

    $test_count = 0;
    foreach ($files as $file) {
      $this->lintFile($root.$file, $linter);
      $test_count++;
    }

    $this->assertTrue(
      ($test_count > 0),
      pht('Expected to find some .lint-test tests in directory %s!', $root));
  }

  private function lintFile($file, ArcanistLinter $linter) {
    $linter = clone $linter;

    $contents = Filesystem::readFile($file);
    $contents = explode("~~~~~~~~~~\n", $contents);
    if (count($contents) < 2) {
      throw new Exception(
        "Expected '~~~~~~~~~~' separating test case and results.");
    }

    list ($data, $expect, $xform, $config) = array_merge(
      $contents,
      array(null, null));

    $basename = basename($file);

    $config = phutil_json_decode($config);
    if (!is_array($config)) {
      throw new Exception(
        "Invalid configuration in test '{$basename}', not valid JSON.");
    }

    PhutilTypeSpec::checkMap(
      $config,
      array(
        'hook' => 'optional bool',
        'config' => 'optional wild',
        'path' => 'optional string',
        'arcconfig' => 'optional map<string, string>',
      ));

    $exception = null;
    $after_lint = null;
    $messages = null;
    $exception_message = false;
    $caught_exception = false;

    try {
      $tmp = new TempFile($basename);
      Filesystem::writeFile($tmp, $data);
      $full_path = (string)$tmp;

      $dir = dirname($full_path);
      $path = basename($full_path);
      $config_file = null;
      $arcconfig = idx($config, 'arcconfig');
      if ($arcconfig) {
        $config_file = json_encode($arcconfig);
      }

      $working_copy = ArcanistWorkingCopyIdentity::newFromRootAndConfigFile(
        $dir,
        $config_file,
        'Unit Test');
      $configuration_manager = new ArcanistConfigurationManager();
      $configuration_manager->setWorkingCopyIdentity($working_copy);


      $engine = new UnitTestableArcanistLintEngine();
      $engine->setWorkingCopy($working_copy);
      $engine->setConfigurationManager($configuration_manager);
      $engine->setPaths(array($path));

      $engine->setCommitHookMode(idx($config, 'hook', false));

      $path_name = idx($config, 'path', $path);
      $linter->addPath($path_name);
      $linter->addData($path_name, $data);
      $config = idx($config, 'config', array());
      foreach ($config as $key => $value) {
        $linter->setLinterConfigurationValue($key, $value);
      }

      $engine->addLinter($linter);
      $engine->addFileData($path_name, $data);

      $results = $engine->run();

      $this->assertEqual(
        1,
        count($results),
        'Expect one result returned by linter.');

      $result = reset($results);
      $patcher = ArcanistLintPatcher::newFromArcanistLintResult($result);
      $after_lint = $patcher->getModifiedFileContent();
    } catch (ArcanistPhutilTestTerminatedException $ex) {
      throw $ex;
    } catch (Exception $exception) {
      $caught_exception = true;
      if ($exception instanceof PhutilAggregateException) {
        $caught_exception = false;
        foreach ($exception->getExceptions() as $ex) {
          if ($ex instanceof ArcanistUsageException) {
            $this->assertSkipped($ex->getMessage());
          } else {
            $caught_exception = true;
          }
        }
      } else if ($exception instanceof ArcanistUsageException) {
        $this->assertSkipped($exception->getMessage());
      }
      $exception_message = $exception->getMessage()."\n\n".
                           $exception->getTraceAsString();
    }

    $this->assertEqual(false, $caught_exception, $exception_message);
    $this->compareLint($basename, $expect, $result);
    $this->compareTransform($xform, $after_lint);
  }

  private function compareLint($file, $expect, ArcanistLintResult $result) {
    $seen = array();
    $raised = array();
    $message_map = array();

    foreach ($result->getMessages() as $message) {
      $sev = $message->getSeverity();
      $line = $message->getLine();
      $char = $message->getChar();
      $code = $message->getCode();
      $name = $message->getName();
      $message_key = $sev.':'.$line.':'.$char;
      $message_map[$message_key] = $message;
      $seen[] = $message_key;
      $raised[] = "  {$sev} at line {$line}, char {$char}: {$code} {$name}";
    }
    $expect = trim($expect);
    if ($expect) {
      $expect = explode("\n", $expect);
    } else {
      $expect = array();
    }
    foreach ($expect as $key => $expected) {
      $expect[$key] = head(explode(' ', $expected));
    }

    $expect = array_fill_keys($expect, true);
    $seen   = array_fill_keys($seen, true);

    if (!$raised) {
      $raised = array('No messages.');
    }
    $raised = "Actually raised:\n".implode("\n", $raised);

    foreach (array_diff_key($expect, $seen) as $missing => $ignored) {
      list($sev, $line, $char) = explode(':', $missing);
      $this->assertFailure(
        "In '{$file}', ".
        "expected lint to raise {$sev} on line {$line} at char {$char}, ".
        "but no {$sev} was raised. {$raised}");
    }

    foreach (array_diff_key($seen, $expect) as $surprising => $ignored) {
      $message = $message_map[$surprising];
      $message_info = $message->getDescription();

      list($sev, $line, $char) = explode(':', $surprising);
      $this->assertFailure(
        "In '{$file}', ".
        "lint raised {$sev} on line {$line} at char {$char}, ".
        "but nothing was expected:\n\n{$message_info}\n\n{$raised}");
    }
  }

  private function compareTransform($expected, $actual) {
    if (!strlen($expected)) {
      return;
    }
    $this->assertEqual(
      $expected,
      $actual,
      'File as patched by lint did not match the expected patched file.');
  }
}
