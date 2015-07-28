<?php

/**
 * Runs unit tests which cover your changes.
 */
final class ArcanistUnitWorkflow extends ArcanistWorkflow {

  const RESULT_OKAY      = 0;
  const RESULT_UNSOUND   = 1;
  const RESULT_FAIL      = 2;
  const RESULT_SKIP      = 3;
  const RESULT_POSTPONED = 4;

  private $unresolvedTests;
  private $testResults;
  private $engine;

  public function getWorkflowName() {
    return 'unit';
  }

  public function getCommandSynopses() {
    return phutil_console_format(<<<EOTEXT
      **unit** [__options__] [__paths__]
      **unit** [__options__] --rev [__rev__]
EOTEXT
      );
  }

  public function getCommandHelp() {
    return phutil_console_format(<<<EOTEXT
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
        'help' => pht(
          'Run unit tests covering changes since a specific revision.'),
        'supports' => array(
          'git',
          'hg',
        ),
        'nosupport' => array(
          'svn' => pht(
            'Arc unit does not currently support %s in SVN.',
            '--rev'),
        ),
      ),
      'engine' => array(
        'param' => 'classname',
        'help' => pht('Override configured unit engine for this project.'),
      ),
      'coverage' => array(
        'help' => pht('Always enable coverage information.'),
        'conflicts' => array(
          'no-coverage' => null,
        ),
      ),
      'no-coverage' => array(
        'help' => pht('Always disable coverage information.'),
      ),
      'detailed-coverage' => array(
        'help' => pht(
          'Show a detailed coverage report on the CLI. Implies %s.',
          '--coverage'),
      ),
      'json' => array(
        'help' => pht('Report results in JSON format.'),
      ),
      'output' => array(
        'param' => 'format',
        'help' => pht(
          "With 'full', show full pretty report (Default). ".
          "With 'json', report results in JSON format. ".
          "With 'ugly', use uglier (but more efficient) JSON formatting. ".
          "With 'none', don't print results."),
        'conflicts' => array(
          'json' => pht('Only one output format allowed'),
          'ugly' => pht('Only one output format allowed'),
        ),
      ),
      'everything' => array(
        'help' => pht('Run every test.'),
        'conflicts' => array(
          'rev' => pht('%s runs all tests.', '--everything'),
        ),
      ),
      'ugly' => array(
        'help' => pht(
          'With %s, use uglier (but more efficient) formatting.',
          '--json'),
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
      $this->getConfigurationManager()->getConfigFromAnySource('unit.engine'));

    if (!$engine_class) {
      throw new ArcanistNoEngineException(
        pht(
          'No unit test engine is configured for this project. Edit %s '.
          'to specify a unit test engine.',
          '.arcconfig'));
    }

    $paths = $this->getArgument('paths');
    $rev = $this->getArgument('rev');
    $everything = $this->getArgument('everything');
    if ($everything && $paths) {
      throw new ArcanistUsageException(
        pht(
          'You can not specify paths with %s. The %s flag runs every test.',
          '--everything',
          '--everything'));
    }

    if ($everything) {
      $paths = iterator_to_array($this->getRepositoryApi()->getAllFiles());
    } else {
      $paths = $this->selectPathsForWorkflow($paths, $rev);
    }

    if (!class_exists($engine_class) ||
        !is_subclass_of($engine_class, 'ArcanistUnitTestEngine')) {
      throw new ArcanistUsageException(
        pht(
          "Configured unit test engine '%s' is not a subclass of '%s'.",
          $engine_class,
          'ArcanistUnitTestEngine'));
    }

    $this->engine = newv($engine_class, array());
    $this->engine->setWorkingCopy($working_copy);
    $this->engine->setConfigurationManager($this->getConfigurationManager());
    if ($everything) {
      $this->engine->setRunAllTests(true);
    } else {
      $this->engine->setPaths($paths);
    }
    $this->engine->setArguments($this->getPassthruArgumentsAsMap('unit'));

    $renderer = new ArcanistUnitConsoleRenderer();
    $this->engine->setRenderer($renderer);

    $enable_coverage = null; // Means "default".
    if ($this->getArgument('coverage') ||
        $this->getArgument('detailed-coverage')) {
      $enable_coverage = true;
    } else if ($this->getArgument('no-coverage')) {
      $enable_coverage = false;
    }
    $this->engine->setEnableCoverage($enable_coverage);

    // Enable possible async tests only for 'arc diff' not 'arc unit'
    if ($this->getParentWorkflow()) {
      $this->engine->setEnableAsyncTests(true);
    } else {
      $this->engine->setEnableAsyncTests(false);
    }

    $results = $this->engine->run();

    $this->validateUnitEngineResults($this->engine, $results);

    $this->testResults = $results;

    $console = PhutilConsole::getConsole();

    $output_format = $this->getOutputFormat();

    if ($output_format !== 'full') {
      $console->disableOut();
    }

    $unresolved = array();
    $coverage = array();
    $postponed_count = 0;
    foreach ($results as $result) {
      $result_code = $result->getResult();
      if ($result_code == ArcanistUnitTestResult::RESULT_POSTPONED) {
        $postponed_count++;
        $unresolved[] = $result;
      } else {
        if ($this->engine->shouldEchoTestResults()) {
          $console->writeOut('%s', $renderer->renderUnitResult($result));
        }
        if ($result_code != ArcanistUnitTestResult::RESULT_PASS) {
          $unresolved[] = $result;
        }
      }
      if ($result->getCoverage()) {
        foreach ($result->getCoverage() as $file => $report) {
          $coverage[$file][] = $report;
        }
      }
    }
    if ($postponed_count) {
      $console->writeOut(
        '%s',
        $renderer->renderPostponedResult($postponed_count));
    }

    if ($coverage) {
      $file_coverage = array_fill_keys(
        $paths,
        0);
      $file_reports = array();
      foreach ($coverage as $file => $reports) {
        $report = ArcanistUnitTestResult::mergeCoverage($reports);
        $cov = substr_count($report, 'C');
        $uncov = substr_count($report, 'U');
        if ($cov + $uncov) {
          $coverage = $cov / ($cov + $uncov);
        } else {
          $coverage = 0;
        }
        $file_coverage[$file] = $coverage;
        $file_reports[$file] = $report;
      }
      $console->writeOut("\n__%s__\n", pht('COVERAGE REPORT'));

      asort($file_coverage);
      foreach ($file_coverage as $file => $coverage) {
        $console->writeOut(
          "    **%s%%**     %s\n",
          sprintf('% 3d', (int)(100 * $coverage)),
          $file);

        $full_path = $working_copy->getProjectRoot().'/'.$file;
        if ($this->getArgument('detailed-coverage') &&
            Filesystem::pathExists($full_path) &&
            is_file($full_path) &&
            array_key_exists($file, $file_reports)) {
          $console->writeOut(
            '%s',
            $this->renderDetailedCoverageReport(
              Filesystem::readFile($full_path),
              $file_reports[$file]));
        }
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
      } else if ($result_code == ArcanistUnitTestResult::RESULT_POSTPONED &&
                 $overall_result != self::RESULT_UNSOUND) {
        $overall_result = self::RESULT_POSTPONED;
      }
    }

    if ($output_format !== 'full') {
      $console->enableOut();
    }
    $data = array_values(mpull($results, 'toDictionary'));
    switch ($output_format) {
      case 'ugly':
        $console->writeOut('%s', json_encode($data));
        break;
      case 'json':
        $json = new PhutilJSON();
        $console->writeOut('%s', $json->encodeFormatted($data));
        break;
      case 'full':
        // already printed
        break;
      case 'none':
        // do nothing
        break;
    }

    return $overall_result;
  }

  public function getUnresolvedTests() {
    return $this->unresolvedTests;
  }

  public function getTestResults() {
    return $this->testResults;
  }

  private function renderDetailedCoverageReport($data, $report) {
    $data = explode("\n", $data);

    $out = '';

    $n = 0;
    foreach ($data as $line) {
      $out .= sprintf('% 5d  ', $n + 1);
      $line = str_pad($line, 80, ' ');
      if (empty($report[$n])) {
        $c = 'N';
      } else {
        $c = $report[$n];
      }
      switch ($c) {
        case 'C':
          $out .= phutil_console_format(
            '<bg:green> %s </bg>',
            $line);
          break;
        case 'U':
          $out .= phutil_console_format(
            '<bg:red> %s </bg>',
            $line);
          break;
        case 'X':
          $out .= phutil_console_format(
            '<bg:magenta> %s </bg>',
            $line);
          break;
        default:
          $out .= ' '.$line.' ';
          break;
      }
      $out .= "\n";
      $n++;
    }

    return $out;
  }

  private function getOutputFormat() {
    if ($this->getArgument('ugly')) {
      return 'ugly';
    }
    if ($this->getArgument('json')) {
      return 'json';
    }
    $format = $this->getArgument('output');
    $known_formats = array(
      'none' => 'none',
      'json' => 'json',
      'ugly' => 'ugly',
      'full' => 'full',
    );
    return idx($known_formats, $format, 'full');
  }


  /**
   * Raise a tailored error when a unit test engine returns results in an
   * invalid format.
   *
   * @param ArcanistUnitTestEngine The engine.
   * @param wild Results from the engine.
   */
  private function validateUnitEngineResults(
    ArcanistUnitTestEngine $engine,
    $results) {

    if (!is_array($results)) {
      throw new Exception(
        pht(
          'Unit test engine (of class "%s") returned invalid results when '.
          'run (with method "%s"). Expected a list of "%s" objects as results.',
          get_class($engine),
          'run()',
          'ArcanistUnitTestResult'));
    }

    foreach ($results as $key => $result) {
      if (!($result instanceof ArcanistUnitTestResult)) {
        throw new Exception(
          pht(
            'Unit test engine (of class "%s") returned invalid results when '.
            'run (with method "%s"). Expected a list of "%s" objects as '.
            'results, but value with key "%s" is not valid.',
            get_class($engine),
            'run()',
            'ArcanistUnitTestResult',
            $key));
      }
    }

  }

}
