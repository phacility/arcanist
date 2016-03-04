<?php

/**
 * Uses the clang static analyzer to lint code.
 */
final class UberStaticAnalysisLinter extends ArcanistFutureLinter {

  private $lintWorkspace;
  private $lintScheme;
  private $buildDestination;
  private $analyzeCommands;

  public function getInfoName() {
    return 'uber-static-analysis';
  }

  public function getInfoURI() {
    return 'https://code.uberinternal.com/diffusion/MOLIN/';
  }

  public function getInfoDescription() {
    return pht('Run the clang static analyzer on ObjC and swift code.');
  }

  public function getLinterName() {
    return 'uber-static-analysis';
  }

  public function getLinterConfigurationName() {
    return 'uber-static-analysis';
  }

  public function getWorkspace() {
    return $this->lintWorkspace;
  }

  public function setWorkspace($workspace) {
    $this->lintWorkspace = $workspace;
    return $this;
  }

  public function getScheme() {
    return $this->lintScheme;
  }

  public function setScheme($scheme) {
    $this->lintScheme = $scheme;
    return $this;
  }

  public function getDefaultDestination() {
    return 'platform=iOS Simulator,name=iPhone 6';
  }

  public function getDestination() {
    return coalesce($this->buildDestination, $this->getDefaultDestination());
  }

  public function setDestination($destination) {
    $this->buildDestination = $destination;
    return $this;
  }

  public function getLinterConfigurationOptions() {
    $options = array(
      'workspace' => array(
        'type' => 'string',
        'help' => pht('The name of the workspace to be analyzed.'),
      ),
      'scheme' => array(
        'type' => 'string',
        'help' => pht('The name of the Xcode scheme to be analyzed.'),
      ),
      'destination' => array(
        'type' => 'optional string',
        'help' => pht('The destination to run static analysis on, defaults to iPhone 6 on the latest SDK.'),
      ),
    );

    return $options + parent::getLinterConfigurationOptions();
  }

  public function setLinterConfigurationValue($key, $value) {
    switch ($key) {
      case 'workspace':
        $this->setWorkspace($value);
        return;
      case 'scheme':
        $this->setScheme($value);
        return;
      case 'destination':
        $this->setDestination($value);
        return;
    }

    return parent::setLinterConfigurationValue($key, $value);
  }

  protected function parseLinterOutput($path, $err, $stdout, $stderr) {
    $pattern = '/^[^:]+:(\d+):(\d+):\swarning:\s(.*)$/';
    $lines = explode("\n", $stderr);
    $errors = array();

    foreach ($lines as $line) {
      if (preg_match($pattern, $line, $matches)) {
        $message = id(new ArcanistLintMessage())
          ->setPath($path)
          ->setLine($matches[1])
          ->setChar($matches[2])
          ->setGranularity(ArcanistLinter::GRANULARITY_FILE)
          ->setSeverity(ArcanistLintSeverity::SEVERITY_ERROR)
          ->setName('Static Analysis Error')
          ->setDescription($matches[3]);
        array_push($errors, $message);
      }
    }
    return $errors;
  }

  private function verifyXcodebuildInstalled() {
    $binary = 'xcodebuild';

    if (!Filesystem::binaryExists($binary)) {
      throw new ArcanistMissingLinterException(
        sprintf("%s\n", pht(
                  'Unable to locate "%s" to run linter %s. You need to install Xcode.',
                  $binary, get_class($this)))
      );
    }
  }

  private function getCommand($handle) {
    $command = '';

    while (!feof($handle)) {
      $line = fgets($handle);
      if (ctype_space($line)) {
        break;
      } else {
        $command = $line;
      }
    }

    return $command;
  }

  private function compilePCH($name, $handle) {
    $command = $this->getCommand($handle);
    exec($command, $output, $return);

    if ($return != 0) {
      throw new Exception(sprintf(
          "Linter failed to compile PCH %s.\n\n%s\n",
          substr($line, 10),
          $output)
      );
    }
  }

  private function parseAnalyzeCommand($handle) {
    $command = $this->getCommand($handle);

    if (preg_match('/--analyze ([^\s]+)(\s|$)/', $command, $match)) {
      $path = $match[1];
      $command = preg_replace('/-Xclang\s-analyzer-output=[^\s]+\s/',
                              '-Xclang -analyzer-output=text ',
                              $command);
      $command = preg_replace('/-o\s[^\s]+(\s|$)/', ' ', $command);
      $this->analyzeCommands[$path] = $command;
    } else {
      throw new Exception(sprintf(
          "Failed to parse analyze command.\n\n%s\n", $command)
      );
    }
  }

  private function getAnalyzeCommands() {
    if ($this->analyzeCommands != NULL)
      return;

    $this->verifyXcodebuildInstalled();
    $this->analyzeCommands = array();
    $command = csprintf('xcodebuild -workspace %s/%s -scheme %s -destination %s -dry-run clean analyze',
                        $this->getProjectRoot(),
                        $this->getWorkspace(),
                        $this->getScheme(),
                        $this->getDestination());
    $handle = popen($command, 'r');

    while ($line = fgets($handle)) {
      if (strncmp($line, "ProcessPCH ", 11) == 0) {
        $this->compilePCH(substr($line, 11), $handle);
      } else if (strncmp($line, "Analyze ", 8) == 0) {
        $this->parseAnalyzeCommand($handle);
      }
    }
    pclose($handle);
  }

  final protected function buildFutures(array $paths) {
    $this->getAnalyzeCommands();
    if (empty($this->analyzeCommands)) {
      throw new Exception(sprintf(
          "%s\n\n",
          pht('Error running the static analyzer.'))
      );
    }

    $futures = array();

    foreach ($paths as $path) {
      $disk_path = $this->getEngine()->getFilePathOnDisk($path);
      if (!array_key_exists($disk_path, $this->analyzeCommands)) {
        throw new Exception(sprintf(
            "%s\n\nNo command for path %s. Is it included in the project?\n",
            pht('Linter failed to parse xcodebuild commands.'),
            $path)
        );
      }
      $future = new ExecFuture('%C', $this->analyzeCommands[$disk_path]);
      $future->setCWD($this->getProjectRoot());
      $futures[$path] = $future;
    }

    return $futures;
  }

  final protected function resolveFuture($path, Future $future) {
    list($err, $stdout, $stderr) = $future->resolve();
    if ($err) {
      $future->resolvex();
    }

    $messages = $this->parseLinterOutput($path, $err, $stdout, $stderr);

    if ($messages === false) {
      if ($err) {
        $future->resolvex();
      } else {
        throw new Exception(
          sprintf(
            "%s\n\nSTDOUT\n%s\n\nSTDERR\n%s",
            pht('Linter failed to parse output!'),
            $stdout,
            $stderr));
      }
    }

    foreach ($messages as $message) {
      $this->addLintMessage($message);
    }
  }

}

?>
