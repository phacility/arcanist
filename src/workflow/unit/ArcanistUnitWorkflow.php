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

/**
 * Runs unit tests which cover your changes.
 *
 * @group workflow
 */
class ArcanistUnitWorkflow extends ArcanistBaseWorkflow {

  const RESULT_OKAY     = 0;
  const RESULT_UNSOUND  = 1;
  const RESULT_FAIL     = 2;
  const RESULT_SKIP     = 3;

  private $unresolvedTests;

  public function getCommandHelp() {
    return phutil_console_format(<<<EOTEXT
      **unit**
          Supports: git, svn
          Run unit tests that cover local changes.
EOTEXT
      );
  }

  public function getArguments() {
    return array(
      'engine' => array(
        'param' => 'classname',
        'help' =>
          "Override configured unit engine for this project."
      ),
      '*' => 'paths',
    );
  }

  public function requiresWorkingCopy() {
    return true;
  }

  public function requiresRepositoryAPI() {
    return true;
  }

  public function run() {

    $working_copy = $this->getWorkingCopy();

    $engine_class = $this->getArgument(
      'engine',
      $working_copy->getConfig('unit_engine'));

    if (!$engine_class) {
      throw new ArcanistNoEngineException(
        "No unit test engine is configured for this project. Edit .arcconfig ".
        "to specify a unit test engine.");
    }

    $repository_api = $this->getRepositoryAPI();

    if ($this->getArgument('paths')) {
      // TODO: deal with git stuff
      $paths = $this->getArgument('paths');
    } else {
      $paths = $repository_api->getWorkingCopyStatus();

      // TODO: clean this up
      foreach ($paths as $path => $mask) {
        if ($mask & ArcanistRepositoryAPI::FLAG_UNTRACKED) {
          unset($paths[$path]);
        }
      }

      $paths = array_keys($paths);
    }


    PhutilSymbolLoader::loadClass($engine_class);
    $engine = newv($engine_class, array());
    $engine->setWorkingCopy($working_copy);
    $engine->setPaths($paths);
    $engine->setArguments($this->getPassthruArgumentsAsMap('unit'));

    $results = $engine->run();

    $status_codes = array(
      ArcanistUnitTestResult::RESULT_PASS => phutil_console_format(
        '   <bg:green>** PASS **</bg>'),
      ArcanistUnitTestResult::RESULT_FAIL => phutil_console_format(
        '   <bg:red>** FAIL **</bg>'),
      ArcanistUnitTestResult::RESULT_SKIP => phutil_console_format(
        '   <bg:yellow>** SKIP **</bg>'),
      ArcanistUnitTestResult::RESULT_BROKEN => phutil_console_format(
        ' <bg:red>** BROKEN **</bg>'),
      ArcanistUnitTestResult::RESULT_UNSOUND => phutil_console_format(
        ' <bg:yellow>** UNSOUND **</bg>'),
      ArcanistUnitTestResult::RESULT_POSTPONED => phutil_console_format(
        ' <bg:yellow>** POSTPONED **</bg>'),
      );

    $unresolved = array();
    foreach ($results as $result) {
      $result_code = $result->getResult();
      echo $status_codes[$result_code].' '.$result->getName()."\n";
      if ($result_code != ArcanistUnitTestResult::RESULT_PASS) {
        echo $result->getUserData()."\n";
        $unresolved[] = $result;
      }
    }
    $this->unresolvedTests = $unresolved;

    $overall_result = self::RESULT_OKAY;
    foreach ($results as $result) {
      $result_code = $result->getResult();
      if ($result_code == ArcanistUnitTestResult::RESULT_FAIL ||
          $result_code == ArcanistUnitTestResult::RESULT_BROKEN) {
        $overall_result = self::RESULT_FAIL;
        break;
      } else if ($result_code == ArcanistUnitTestResult::RESULT_UNSOUND) {
        $overall_result = self::RESULT_UNSOUND;
      }
    }

    return $overall_result;
  }

  public function getUnresolvedTests() {
    return $this->unresolvedTests;
  }

}
