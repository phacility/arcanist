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
  protected function getLinter() {
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
  protected function executeTestsInDirectory($root) {
    $linter = $this->getLinter();

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

    list($data, $expect, $xform, $config) = array_merge(
      $contents,
      array(null, null));

    if ($config) {
      $config = phutil_json_decode($config);
    } else {
      $config = array();
    }
    PhutilTypeSpec::checkMap(
      $config,
      array(
        'config' => 'optional map<string, wild>',
        'mode' => 'optional string',
        'path' => 'optional string',
        'stopped' => 'optional bool',
      ));

    $exception = null;
    $after_lint = null;
    $messages = null;
    $exception_message = false;
    $caught_exception = false;

    try {
      $path_name = idx($config, 'path');

      if ($path_name !== null) {
        $basename = basename($path_name);
      } else {
        $basename = basename($file);
      }

      $tmp = new TempFile($basename);
      Filesystem::writeFile($tmp, $data);
      $full_path = (string)$tmp;

      $mode = idx($config, 'mode');
      if ($mode) {
        Filesystem::changePermissions($tmp, octdec($mode));
      }

      $dir = dirname($full_path);

      $working_copy = ArcanistWorkingCopyIdentity::newFromRootAndConfigFile(
        $dir,
        null,
        pht('Unit Test'));
      $configuration_manager = new ArcanistConfigurationManager();
      $configuration_manager->setWorkingCopyIdentity($working_copy);

      $engine = new ArcanistUnitTestableLintEngine();
      $engine->setWorkingCopy($working_copy);
      $engine->setConfigurationManager($configuration_manager);

      $engine->setPaths(array($basename));

      $linter->setEngine($engine);
      $linter->addPath($basename);
      $linter->addData($basename, $data);

      foreach (idx($config, 'config', array()) as $key => $value) {
        $linter->setLinterConfigurationValue($key, $value);
      }

      $engine->addLinter($linter);
      $engine->addFileData($basename, $data);

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

  private function compareLint($file, $expect, ArcanistLintResult $results) {
    $expected_results = new ArcanistLintResult();

    $expect = trim($expect);
    if ($expect) {
      $expect = explode("\n", $expect);
    } else {
      $expect = array();
    }

    foreach ($expect as $result) {
      $parts = explode(':', $result);

      $message = new ArcanistLintMessage();

      $severity = idx($parts, 0);
      $line = idx($parts, 1);
      if ($line === '') {
        $line = null;
      }

      $char = idx($parts, 2);
      if ($char === '') {
        $char = null;
      }

      $code = idx($parts, 3);
      if ($code === '') {
        $code = null;
      }

      if ($severity !== null) {
        $message->setSeverity($severity);
      }

      if ($line !== null) {
        $message->setLine($line);
      }

      if ($char !== null) {
        $message->setChar($char);
      }

      if ($code !== null) {
        $message->setCode($code);
      }

      $expected_results->addMessage($message);
    }

    $missing    = array();
    $surprising = $results->getMessages();

    // TODO: Make this more efficient.
    foreach ($expected_results->getMessages() as $expected_message) {
      $found = false;

      foreach ($results->getMessages() as $ii => $actual_message) {
        if (!self::compareLintMessageProperty(
          $expected_message->getSeverity(),
          $actual_message->getSeverity())) {

          continue;
        }

        if (!self::compareLintMessageProperty(
          $expected_message->getLine(),
          $actual_message->getLine())) {

          continue;
        }

        if (!self::compareLintMessageProperty(
          $expected_message->getChar(),
          $actual_message->getChar())) {

          continue;
        }

        if (!self::compareLintMessageProperty(
          $expected_message->getCode(),
          $actual_message->getCode())) {

          continue;
        }

        $found = true;
        unset($surprising[$ii]);
      }

      if (!$found) {
        $missing[] = $expected_message;
      }
    }

    if ($missing || $surprising) {
      $this->assertFailure(
        sprintf(
          "%s\n%s%s",
          pht(
            'Lint emitted an unexpected set of messages for file "%s".',
            $file),
          $this->renderMessages(pht('MISSING MESSAGES'), $missing),
          $this->renderMessages(pht('SURPLUS MESSAGES'), $surprising)));
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

  /**
   * Compare properties of @{class:ArcanistLintMessage} instances.
   *
   * @param  wild
   * @param  wild
   * @return bool
   */
  private static function compareLintMessageProperty($x, $y) {
    if ($x === null) {
      return true;
    }

    return ($x === $y);
  }

  private function renderMessages($header, array $messages) {
    if (!$messages) {
      $display = tsprintf(
        "%s\n",
        pht('(No messages.)'));
    } else {
      $lines = array();
      foreach ($messages as $message) {
        $line = $message->getLine();
        if ($line === null) {
          $display_line = pht('<null>');
        } else {
          $display_line = $line;
        }

        $char = $message->getChar();
        if ($char === null) {
          $display_char = pht('<null>');
        } else {
          $display_char = $char;
        }

        $code = $message->getCode();
        $name = $message->getName();
        if ($code !== null && $name !== null) {
          $display_code = pht('%s: %s', $code, $name);
        } else if ($code !== null) {
          $display_code = pht('%s', $code);
        } else {
          $display_code = null;
        }

        $severity = $message->getSeverity();

        if ($display_code === null) {
          $display_message = pht(
            'Message with severity "%s" at "%s:%s"',
            $severity,
            $display_line,
            $display_char);
        } else {
          $display_message = pht(
            'Message with severity "%s" at "%s:%s" (%s)',
            $severity,
            $display_line,
            $display_char,
            $display_code);
        }

        $lines[] = tsprintf(
          "    %s\n",
          $display_message);
      }
      $display = implode('', $lines);
    }

    return tsprintf(
      "%s\n%B\n",
      $header,
      $display);
  }

}
