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
 * Runs unit tests which cover your changes.
 *
 * @group workflow
 */
final class ArcanistUnitWorkflow extends ArcanistBaseWorkflow {

  const RESULT_OKAY      = 0;
  const RESULT_UNSOUND   = 1;
  const RESULT_FAIL      = 2;
  const RESULT_SKIP      = 3;
  const RESULT_POSTPONED = 4;

  private $unresolvedTests;
  private $engine;

  public function getCommandHelp() {
    return phutil_console_format(<<<EOTEXT
      **unit** [__options__] [__paths__]
      **unit** [__options__] --rev [__rev__]
          Supports: git, svn, hg
          Run unit tests that cover specified paths. If no paths are specified,
          unit tests covering all modified files will be run.
EOTEXT
      );
  }

  public function getArguments() {
    return array(
      'rev' => array(
        'param' => 'revision',
        'help' => "Run unit tests covering changes since a specific revision.",
        'supports' => array(
          'git',
          'hg',
        ),
        'nosupport' => array(
          'svn' => "Arc unit does not currently support --rev in SVN.",
        ),
      ),
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

  public function getEngine() {
    return $this->engine;
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

    $paths = $this->getArgument('paths');
    $rev = $this->getArgument('rev');

    $paths = $this->selectPathsForWorkflow($paths, $rev);

    PhutilSymbolLoader::loadClass($engine_class);
    if (!is_subclass_of($engine_class, 'ArcanistBaseUnitTestEngine')) {
      throw new ArcanistUsageException(
        "Configured unit test engine '{$engine_class}' is not a subclass of ".
        "'ArcanistBaseUnitTestEngine'.");
    }

    $this->engine = newv($engine_class, array());
    $this->engine->setWorkingCopy($working_copy);
    $this->engine->setPaths($paths);
    $this->engine->setArguments($this->getPassthruArgumentsAsMap('unit'));

    // Enable possible async tests only for 'arc diff' not 'arc unit'
    if ($this->getParentWorkflow()) {
      $this->engine->setEnableAsyncTests(true);
    } else {
      $this->engine->setEnableAsyncTests(false);
    }

    $results = $this->engine->run();

    $status_codes = array(
      ArcanistUnitTestResult::RESULT_PASS => phutil_console_format(
        '<bg:green>** PASS **</bg>'),
      ArcanistUnitTestResult::RESULT_FAIL => phutil_console_format(
        '<bg:red>** FAIL **</bg>'),
      ArcanistUnitTestResult::RESULT_SKIP => phutil_console_format(
        '<bg:yellow>** SKIP **</bg>'),
      ArcanistUnitTestResult::RESULT_BROKEN => phutil_console_format(
        '<bg:red>** BROKEN **</bg>'),
      ArcanistUnitTestResult::RESULT_UNSOUND => phutil_console_format(
        '<bg:yellow>** UNSOUND **</bg>'),
      ArcanistUnitTestResult::RESULT_POSTPONED => phutil_console_format(
        '<bg:yellow>** POSTPONED **</bg>'),
      );

    $unresolved = array();
    $postponed_count = 0;
    foreach ($results as $result) {
      $result_code = $result->getResult();
      if ($result_code == ArcanistUnitTestResult::RESULT_POSTPONED) {
        $postponed_count++;
        $unresolved[] = $result;
      } else {
        if ($this->engine->shouldEchoTestResults()) {
          echo '  '.$status_codes[$result_code];
          if ($result_code == ArcanistUnitTestResult::RESULT_PASS) {
            echo ' '.self::formatTestDuration($result->getDuration());
          }
          echo ' '.$result->getName()."\n";
        }
        if ($result_code != ArcanistUnitTestResult::RESULT_PASS) {
          if ($this->engine->shouldEchoTestResults()) {
            echo $result->getUserData()."\n";
          }
          $unresolved[] = $result;
        }
      }
    }
    if ($postponed_count) {
      echo sprintf("%s %d %s\n",
         $status_codes[ArcanistUnitTestResult::RESULT_POSTPONED],
         $postponed_count,
         ($postponed_count > 1)?'tests':'test');
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
      } else if ($result_code == ArcanistUnitTestResult::RESULT_POSTPONED &&
                 $overall_result != self::RESULT_UNSOUND) {
        $overall_result = self::RESULT_POSTPONED;
      }
    }

    return $overall_result;
  }

  public function getUnresolvedTests() {
    return $this->unresolvedTests;
  }

  public function setDifferentialDiffID($id) {
    if ($this->engine) {
      $this->engine->setDifferentialDiffID($id);
    }
  }

  private static function formatTestDuration($seconds) {
    // Very carefully define inclusive upper bounds on acceptable unit test
    // durations. Times are in milliseconds and are in increasing order.
    $acceptableness = array(
      50   => "<fg:green>%s</fg><fg:yellow>\xE2\x98\x85</fg> ",
      200  => '<fg:green>%s</fg>  ',
      500  => '<fg:yellow>%s</fg>  ',
      INF  => '<fg:red>%s</fg>  ',
    );

    $milliseconds = $seconds * 1000;
    $duration = self::formatTime($seconds);
    foreach ($acceptableness as $upper_bound => $formatting) {
      if ($milliseconds <= $upper_bound) {
        return phutil_console_format($formatting, $duration);
      }
    }
    return phutil_console_format(end($acceptableness), $duration);
  }

  private static function formatTime($seconds) {
    if ($seconds >= 60) {
      $minutes = floor($seconds / 60);
      return sprintf('%dm%02ds', $minutes, round($seconds % 60));
    }

    if ($seconds >= 1) {
      return sprintf('%4.1fs', $seconds);
    }

    $milliseconds = $seconds * 1000;
    if ($milliseconds >= 1) {
      return sprintf('%3dms', round($milliseconds));
    }

    return ' <1ms';
  }
}
