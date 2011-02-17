<?php

/*
 * Copyright 2011 Facebook, Inc.
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

abstract class ArcanistLinterTestCase extends ArcanistPhutilTestCase {

  public function executeTestsInDirectory($root, $linter, $working_copy) {
    foreach (Filesystem::listDirectory($root, $hidden = false) as $file) {
      $this->lintFile($root.$file, $linter, $working_copy);
    }
  }

  private function lintFile($file, $linter, $working_copy) {
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

    if ($config) {
      $config = json_decode($config, true);
      if (!is_array($config)) {
        throw new Exception(
          "Invalid configuration in test '{$basename}', not valid JSON.");
      }
    } else {
      $config = array();
    }

    /* TODO: ?
    validate_parameter_list(
      $config,
      array(
      ),
      array(
        'project' => true,
        'path' => true,
        'hook' => true,
      ));
    */

    $exception = null;
    $after_lint = null;
    $messages = null;
    $exception_message = false;
    $caught_exception = false;
    try {

      $path = idx($config, 'path', 'lint/'.$basename.'.php');

      $engine = new UnitTestableArcanistLintEngine();
      $engine->setWorkingCopy($working_copy);
      $engine->setPaths(array($path));

      $engine->setCommitHookMode(idx($config, 'hook', false));

      $linter->addPath($path);
      $linter->addData($path, $data);

      $engine->addLinter($linter);
      $engine->addFileData($path, $data);

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
      $exception_message = $exception->getMessage()."\n\n".
                           $exception->getTraceAsString();
    }

    switch ($basename) {
      default:
        $this->assertEqual(false, $caught_exception, $exception_message);
        $this->compareLint($basename, $expect, $result);
        $this->compareTransform($xform, $after_lint);
        break;
    }
  }

  private function compareLint($file, $expect, $result) {
    $seen = array();
    $raised = array();
    foreach ($result->getMessages() as $message) {
      $sev = $message->getSeverity();
      $line = $message->getLine();
      $char = $message->getChar();
      $code = $message->getCode();
      $name = $message->getName();
      $seen[] = $sev.":".$line.":".$char;
      $raised[] = "  {$sev} at line {$line}, char {$char}: {$code} {$name}";
    }
    $expect = trim($expect);
    if ($expect) {
      $expect = explode("\n", $expect);
    } else {
      $expect = array();
    }
    foreach ($expect as $key => $expected) {
      $expect[$key] = reset(explode(' ', $expected));
    }

    $expect = array_fill_keys($expect, true);
    $seen   = array_fill_keys($seen, true);

    if (!$raised) {
      $raised = array("No messages.");
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
      list($sev, $line, $char) = explode(':', $surprising);
      $this->assertFailure(
        "In '{$file}', ".
        "lint raised {$sev} on line {$line} at char {$char}, ".
        "but nothing was expected. {$raised}");
    }
  }

  private function compareTransform($expected, $actual) {
    if (!strlen($expected)) {
      return;
    }
    $this->assertEqual(
      $expected,
      $actual,
      "File as patched by lint did not match the expected patched file.");
  }
}
