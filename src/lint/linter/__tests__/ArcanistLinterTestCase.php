<?php

/**
 * Facilitates implementation of test cases for @{class:ArcanistLinter}s.
 */
abstract class ArcanistLinterTestCase extends PhutilTestCase {

  /**
   * Returns an instance of the linter being tested.
   *
   * @return ArcanistLinter
   */
  final protected function getLinter() {
    $matches = null;
    if (!preg_match('/^(\w+Linter)TestCase$/', get_class($this), $matches) ||
        !is_subclass_of($matches[1], 'ArcanistLinter')) {
      throw new Exception(pht('Unable to infer linter class name.'));
    }

    return newv($matches[1], array());
  }

  abstract public function testLinter();

  /**
   * Executes all tests from the specified subdirectory. If a linter is not
   * explicitly specified, it will be inferred from the name of the test class.
   */
  public function executeTestsInDirectory(
    $root,
    ArcanistLinter $linter = null) {

    if (!$linter) {
      $linter = $this->getLinter();
    }

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
      pht(
        'Expected to find some %s tests in directory %s!',
        '.lint-test',
        $root));
  }

  private function lintFile($file, ArcanistLinter $linter) {
    $linter = clone $linter;

    $contents = Filesystem::readFile($file);
    $contents = preg_split('/^~{4,}\n/m', $contents);
    if (count($contents) < 2) {
      throw new Exception(
        pht(
          "Expected '%s' separating test case and results.",
          '~~~~~~~~~~'));
    }

    list ($data, $expect, $xform, $config) = array_merge(
      $contents,
      array(null, null));

    $basename = basename($file);

    if ($config) {
      $config = phutil_json_decode($config);
    } else {
      $config = array();
    }
    PhutilTypeSpec::checkMap(
      $config,
      array(
        'config' => 'optional map<string, wild>',
        'path' => 'optional string',
        'mode' => 'optional string',
        'stopped' => 'optional bool',
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

      $mode = idx($config, 'mode');
      if ($mode) {
        Filesystem::changePermissions($tmp, octdec($mode));
      }

      $dir = dirname($full_path);
      $path = basename($full_path);

      $working_copy = ArcanistWorkingCopyIdentity::newFromRootAndConfigFile(
        $dir,
        null,
        pht('Unit Test'));
      $configuration_manager = new ArcanistConfigurationManager();
      $configuration_manager->setWorkingCopyIdentity($working_copy);


      $engine = new ArcanistUnitTestableLintEngine();
      $engine->setWorkingCopy($working_copy);
      $engine->setConfigurationManager($configuration_manager);

      $path_name = idx($config, 'path', $path);
      $engine->setPaths(array($path_name));

      $linter->addPath($path_name);
      $linter->addData($path_name, $data);

      foreach (idx($config, 'config', array()) as $key => $value) {
        $linter->setLinterConfigurationValue($key, $value);
      }

      $engine->addLinter($linter);
      $engine->addFileData($path_name, $data);

      $results = $engine->run();
      $this->assertEqual(
        1,
        count($results),
        pht('Expect one result returned by linter.'));

      $assert_stopped = idx($config, 'stopped');
      if ($assert_stopped !== null) {
        $this->assertEqual(
          $assert_stopped,
          $linter->didStopAllLinters(),
          $assert_stopped
            ? pht('Expect linter to be stopped.')
            : pht('Expect linter to not be stopped.'));
      }

      $result = reset($results);
      $patcher = ArcanistLintPatcher::newFromArcanistLintResult($result);
      $after_lint = $patcher->getModifiedFileContent();
    } catch (PhutilTestTerminatedException $ex) {
      throw $ex;
    } catch (Exception $exception) {
      $caught_exception = true;
      if ($exception instanceof PhutilAggregateException) {
        $caught_exception = false;
        foreach ($exception->getExceptions() as $ex) {
          if ($ex instanceof ArcanistUsageException ||
              $ex instanceof ArcanistMissingLinterException) {
            $this->assertSkipped($ex->getMessage());
          } else {
            $caught_exception = true;
          }
        }
      } else if ($exception instanceof ArcanistUsageException ||
                 $exception instanceof ArcanistMissingLinterException) {
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
      $raised[] = sprintf(
        '  %s: %s %s',
        pht('%s at line %d, char %d', $sev, $line, $char),
        $code,
        $name);
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
      $raised = array(pht('No messages.'));
    }
    $raised = sprintf(
      "%s:\n%s",
      pht('Actually raised'),
      implode("\n", $raised));

    foreach (array_diff_key($expect, $seen) as $missing => $ignored) {
      $missing = explode(':', $missing);
      $sev = array_shift($missing);
      $pos = $missing;

      $this->assertFailure(
        pht(
          "In '%s', expected lint to raise %s on line %d at char %d, ".
          "but no %s was raised. %s",
          $file,
          $sev,
          idx($pos, 0),
          idx($pos, 1),
          $sev,
          $raised));
    }

    foreach (array_diff_key($seen, $expect) as $surprising => $ignored) {
      $message = $message_map[$surprising];
      $message_info = $message->getDescription();

      list($sev, $line, $char) = explode(':', $surprising);
      $this->assertFailure(
        sprintf(
          "%s:\n\n%s\n\n%s",
          pht(
            "In '%s', lint raised %s on line %d at char %d, ".
            "but nothing was expected",
            $file,
            $sev,
            $line,
            $char),
          $message_info,
          $raised));
    }
  }

  private function compareTransform($expected, $actual) {
    if (!strlen($expected)) {
      return;
    }
    $this->assertEqual(
      $expected,
      $actual,
      pht('File as patched by lint did not match the expected patched file.'));
  }

}
