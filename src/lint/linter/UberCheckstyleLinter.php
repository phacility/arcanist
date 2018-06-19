<?php

/**
 * This linter invokes checkstyle for verifying code style standards.
 */
class UberCheckstyleLinter extends ArcanistFutureLinter {

  private $checkstyleScript = null;
  private $checkstyleJar = null;
  private $checkstyleConfig = null;
  private $checkstyleURL = null;
  private $useScript = false;
  private $maxFiles = 100;

  public function getInfoName() {
    return 'Java checkstyle linter';
  }

  public function getLinterName() {
    return 'CHECKSTYLE';
  }

  public function getInfoURI() {
    return 'http://checkstyle.sourceforge.net';
  }

  public function getInfoDescription() {
    return 'Use checkstyle to perform static analysis on Java code';
  }

  public function getLinterConfigurationName() {
    return 'checkstyle';
  }

  public function getLinterConfigurationOptions() {
    $options = array(
      'checkstyle.script' => array(
        'type' => 'optional string',
        'help' => pht('Checkstyle script to execute. Script must output checkstyle in XML to $stdout'),
      ),
      'checkstyle.jar' => array(
        'type' => 'optional string',
        'help' => pht('Checkstyle jar [from https://sourceforge.net/projects/checkstyle/files/checkstyle/]'),
      ),
      'checkstyle.config' => array(
        'type' => 'optional string',
        'help' => pht('Checkstyle configuration file'),
      ),
      'checkstyle.maxfiles' => array(
        'type' => 'optional int',
        'help' => pht('The maximum number of files to check per call of checkstyle. If there are more files, they will be split up into multiple checkstyle invocations.'),
      ),
      'checkstyle.url' => array(
        'type' => 'optional string',
        'help' => pht('If $checkstyle.jar doesn\'t exist and ' .
                '$checkstyle.url is populated, download the jar ' .
                'from this URL'),
      ),
    );
    return $options + parent::getLinterConfigurationOptions();
  }

  public function setLinterConfigurationValue($key, $value) {
    switch ($key) {
      case 'checkstyle.script':
        $this->checkstyleScript = $value;
        return;
      case 'checkstyle.jar':
        $this->checkstyleJar = $value;
        return;
      case 'checkstyle.config':
        $this->checkstyleConfig = $value;
        return;
      case 'checkstyle.maxfiles':
        $this->maxFiles = $value;
        return;
      case 'checkstyle.url':
        $this->checkstyleURL = $value;
        return;
    }
    return parent::setLinterConfigurationValue($key, $value);
  }

  /**
   * Check that the checkstyle libraries are installed.
   * @return void
   */
  private function checkConfiguration() {
    if ($this->checkstyleJar != null) {
      if ($this->checkstyleConfig != null) {
        $this->checkCheckstyleConfig($this->checkstyleConfig);
      }
      $this->checkJavaConfiguration();
      $this->checkJarConfiguration($this->checkstyleJar, $this->checkstyleURL);
    } else if ($this->checkstyleScript != null) {
      $this->checkScriptConfiguration();
      $this->useScript = true;
    } else {
      throw new ArcanistMissingLinterException(
        pht('Missing config.  Either \'checkstyle.script\' or \'checkstyle.jar\' need to be set. ')
      );
    }
  }

  private function checkCheckstyleConfig($checkstyleConfig) {
    $absScriptPath = Filesystem::resolvePath($checkstyleConfig, $this->getProjectRoot());
    if (!Filesystem::pathExists($absScriptPath)) {
      throw new InvalidArgumentException(
        pht(
          'Unable to locate checkstyle config "%s" to run linter %s. ' .
          'Either set it to a valid location in your linter ' .
          'configuration or leave it unset.',
          $absScriptPath,
          get_class($this)));
    }
  }

  private function checkScriptConfiguration() {
    // Some scripts have command line arguments
    $script = explode(' ', $this->checkstyleScript)[0];
    $absScriptPath = Filesystem::resolvePath($script, $this->getProjectRoot());
    if (!Filesystem::pathExists($absScriptPath)) {
      throw new ArcanistMissingLinterException(
        pht(
          'Unable to locate script "%s" to run linter %s. You may need ' .
          'to install the script, or adjust your linter configuration.',
          $absScriptPath,
          get_class($this)));
    }
  }

  private function checkJavaConfiguration() {
    if (!Filesystem::binaryExists("java")) {
      throw new ArcanistMissingLinterException(
        pht('Java is not installed', get_class($this)));
    }
  }

  private function downloadCheckstyleJar($checkstyleJar, $checkstyleURL) {
    $absJarPath = Filesystem::resolvePath($checkstyleJar, $this->getProjectRoot());
    if(!is_writable(dirname($absJarPath))){
      throw new InvalidArgumentException(
        pht('Checkstyle jar cannot be written to "%s"', $checkstyleJar)
      );
    }

    // Stage and validate to avoid file_put_contents leaving around an empty file
    // if the URL is bad etc
    $tmpFile = tempnam(sys_get_temp_dir(), 'TMP_');
    $bytesDownloaded = file_put_contents($tmpFile, fopen($this->checkstyleURL, 'r'));
    if ($bytesDownloaded == 0) {
      unlink($tmpFile);
      throw new ArcanistMissingLinterException(
        pht(
          'Unable to download jar "%s" from "%s" ' .
          'to run linter %s. Make sure `checkstyle.url` is set ' .
          'to a valid URL for the checkstyle binary (ie: ' .
          'https://sourceforge.net/projects/checkstyle/files/checkstyle/8.2/checkstyle-8.2-all.jar)',
          $absJarPath,
          $checkstyleURL,
          get_class($this))
      );
    } else {
      rename($tmpFile, $absJarPath);
    }
  }

  private function checkJarConfiguration($checkstyleJar, $checkstyleURL) {
    $absJarPath = Filesystem::resolvePath($checkstyleJar, $this->getProjectRoot());
    if (!Filesystem::pathExists($absJarPath)) {
      if ($checkstyleURL) {
        $this->downloadCheckstyleJar($checkstyleJar, $checkstyleURL);
      } else {
        throw new ArcanistMissingLinterException(
          sprintf(
            "%s\n\n%s\n",
            pht(
              'Unable to locate jar "%s" to run linter %s. ' .
              'Either download the checkstyle binary manually ' .
              'or adjust your linter configuration. (see ' .
              'checkstyle.jar and checkstyle.url in .arclint)',
              $absJarPath,
              get_class($this)),
            pht("TO FIX:\n" .
              "Download checkstyle jar from https://sourceforge" .
              "net/projects/checkstyle/files/checkstyle/\n" .
              "-or-\n" .
              "Set `checkstyle.url` in `.arclint` to a valid " .
              "URL for the checkstyle binary\n" .
              "(ie: https://sourceforge.net/projects/" .
              "checkstyle/files/checkstyle/8.2/" .
              "checkstyle-8.2-all.jar)"
        )));
      }
    }
  }

  private function getCommand() {
    if ($this->useScript === true) {
      return $this->checkstyleScript;
    }  else {
      $command = sprintf('java -jar %s -f xml ', $this->checkstyleJar);
      if($this->checkstyleConfig) {
        $command .= sprintf('-c %s ', $this->checkstyleConfig);
      }
      
      return $command;
    }
  }

  final protected function buildFutures(array $paths) {
    $this->checkConfiguration();
    $futures = array();
    // Call checkstyle in batches
    $chunks = array_chunk($paths, 100);

    $command = $this->getCommand();
    foreach ($chunks as $chunk) {
      $future = new ExecFuture(sprintf("%s %s", $command, implode(" ", $chunk)));
      $future->setCWD($this->getProjectRoot());

      foreach ($chunk as $path) {
        $futures[$path] = $future;
      }
    }

    return $futures;
  }

  final protected function resolveFuture($path, Future $future) {
    list($err, $stdout, $stderr) = $future->resolve();
    $this->parseLinterOutput($path, $err, $stdout, $stderr);
  }

  protected function parseLinterOutput($path, $err, $stdout, $stderr) {
    // Strip out last line of Checkstyle XML output [see: https://github.com/checkstyle/checkstyle/issues/1018]
    if(strpos($stdout, 'Checkstyle ends') !== false) {
      $stdout = substr($stdout, 0, strrpos($stdout, "Checkstyle ends"));
    }
    $dom = new DOMDocument();
    @$dom->loadXML($stdout);

    $files = $dom->getElementsByTagName('file');
    foreach ($files as $file) {
      $errors = $file->getElementsByTagName('error');
      $name = $file->getAttribute('name');
      $arcPath = ltrim(str_replace(getcwd(), '', $name), '/');

      if ($arcPath != $path) {
        continue;
      }

      $changedLines = $this->getEngine()->getPathChangedLines($arcPath);
      if ($changedLines) {
        $changedLines = array_keys($changedLines);
      } else {
        $changedLines = array();
      }

      foreach ($errors as $error) {
        $source = idx(array_slice(explode('.', $error->getAttribute('source')), -1), 0);
        $line = $error->getAttribute('line');

        // Do not fail for errors outside changed lines
        $errorTypes = array(
          'JavadocTypeCheck',
          'JavadocMethodCheck',
          'RegexpSinglelineCheck'
        );
        if (in_array($source, $errorTypes) && !in_array($line, $changedLines)) {
          continue;
        }

        // checkstyle's XMLLogger escapes these five characters
        $description = $error->getAttribute('message');
        $description = str_replace(
          ['&lt;', '&gt;', '&apos;', '&quot;', '&amp;'],
          ['<', '>', '\'', '"', '&'],
          $description);

        $message = id(new ArcanistLintMessage())
          ->setPath($path)
          ->setLine($line)
          ->setCode($this->getLinterName())
          ->setName($source)
          ->setDescription($description);

        $column = $error->getAttribute('column');
        if ($column) {
          $message->setChar($column);
        }

        $severity = $error->getAttribute('severity');
        switch ($severity) {
          case 'error':
            $message->setSeverity(ArcanistLintSeverity::SEVERITY_ERROR);
            break;
          case 'info':
            $message->setSeverity(ArcanistLintSeverity::SEVERITY_ADVICE);
            break;
          case 'ignore':
            $message->setSeverity(ArcanistLintSeverity::SEVERITY_DISABLED);
            break;
          case 'warning':
          default:
            $message->setSeverity(ArcanistLintSeverity::SEVERITY_WARNING);
            break;
        }

        $this->addAutofixes($message, $source, $line, $path);
        $this->addLintMessage($message);
      }
    }
  }

  private function addAutofixes($message, $source, $line, $path) {
    // We only autofix these specific classes of problems
    if (!in_array($source, ["UnusedImportsCheck", "RegexpMultilineCheck"])) {
      return;
    }

    $file = new SplFileObject($path);
    $origLines = '';
    if ($source == "UnusedImportsCheck") {
      $file->seek($line - 1); // arcanist line number starts with 1, but file starts with 0
      $origLines = $file->current();
    } else if ($source == "RegexpMultilineCheck") {
      // checkstyle reports line before blank line
      // let's set message to point at the first blank line
      $message->setLine($line + 1);
      // seek to line *after* first blank line
      $curLine = $line + 1;
      $file->seek($curLine);
      // collect all the blank lines we want to delete
      while ($file->current() == "\n") {
        $origLines = $origLines . "\n";
        $curLine = $curLine + 1;
        $file->seek($curLine);
      }
    }
    $message->setChar(null);
    $message->setOriginalText($origLines);
    $message->setReplacementText('');
  }
}
