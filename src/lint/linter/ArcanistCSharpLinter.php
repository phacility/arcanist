<?php

/**
 * C# linter for Arcanist.
 *
 * @group linter
 */
final class ArcanistCSharpLinter extends ArcanistFutureLinter {

  private $runtimeEngine;
  private $cslintEngine;
  private $cslintHintPath;
  private $loaded;
  private $discoveryMap;

  public function getLinterName() {
    return 'C#';
  }

  public function getLinterConfigurationName() {
    return 'csharp';
  }

  public function getLinterConfigurationOptions() {
    $options = parent::getLinterConfigurationOptions();

    $options["discovery"] = 'map<string, list<string>>';
    $options["binary"] = 'string';

    return $options;
  }

  public function setLinterConfigurationValue($key, $value) {
    switch ($key) {
      case 'discovery':
        $this->discoveryMap = $value;
        return;
      case 'binary':
        $this->cslintHintPath = $value;
        return;
    }
    parent::setLinterConfigurationValue($key, $value);
  }

  public function getLintCodeFromLinterConfigurationKey($code) {
    return $code;
  }

  public function setCustomSeverityMap(array $map) {
    foreach ($map as $code => $severity) {
      if (substr($code, 0, 2) === "SA" && $severity == "disabled") {
        throw new Exception(
          "In order to keep StyleCop integration with IDEs and other tools ".
          "consistent with Arcanist results, you aren't permitted to ".
          "disable StyleCop rules within '.arclint'.  ".
          "Instead configure the severity using the StyleCop settings dialog ".
          "(usually accessible from within your IDE).  StyleCop settings ".
          "for your project will be used when linting for Arcanist.");
      }
    }
    return parent::setCustomSeverityMap($map);
  }

  public function getLintSeverityMap() {
    return array();
  }

  public function getLintNameMap() {
    return array();
  }

  /**
   * Determines what executables and lint paths to use.  Between platforms
   * this also changes whether the lint engine is run under .NET or Mono.  It
   * also ensures that all of the required binaries are available for the lint
   * to run successfully.
   *
   * @return void
   */
  private function loadEnvironment() {
    if ($this->loaded) {
      return;
    }

    // Determine runtime engine (.NET or Mono).
    if (phutil_is_windows()) {
      $this->runtimeEngine = "";
    } else if (Filesystem::binaryExists("mono")) {
      $this->runtimeEngine = "mono ";
    } else {
      throw new Exception("Unable to find Mono and you are not on Windows!");
    }

    // Determine cslint path.
    $cslint = $this->cslintHintPath;
    if ($cslint !== null && file_exists($cslint)) {
      $this->cslintEngine = Filesystem::resolvePath($cslint);
    } else if (Filesystem::binaryExists("cslint.exe")) {
      $this->cslintEngine = "cslint.exe";
    } else {
      throw new Exception("Unable to locate cslint.");
    }

    $this->loaded = true;
  }

  protected function buildFutures(array $paths) {
    $this->loadEnvironment();

    $futures = array();

    foreach ($paths as $path) {
      // %s won't pass through the JSON correctly
      // under Windows.  This is probably because not only
      // does the JSON have quotation marks in the content,
      // but because there'll be a lot of escaping and
      // double escaping because the JSON also contains
      // regular expressions.  cslint supports passing the
      // settings JSON through base64-encoded to mitigate
      // this issue.
      $futures[$path] = new ExecFuture(
        "%C --settings-base64=%s -r=. %s",
        $this->runtimeEngine.$this->cslintEngine,
        base64_encode(json_encode($this->discoveryMap)),
        $this->getEngine()->getFilePathOnDisk($path));
    }

    return $futures;
  }

  protected function resolveFuture($path, Future $future) {
    list($rc, $stdout) = $future->resolve();
    $results = json_decode($stdout);
    if ($results === null || $results->Issues === null) {
      return;
    }
    foreach ($results->Issues as $issue) {
      $message = new ArcanistLintMessage();
      $message->setPath($path);
      $message->setLine($issue->LineNumber);
      $message->setCode($issue->Index->Code);
      $message->setName($issue->Index->Name);
      $message->setChar($issue->Column);
      $message->setOriginalText($issue->OriginalText);
      $message->setReplacementText($issue->ReplacementText);
      $message->setDescription(
        vsprintf($issue->Index->Message, $issue->Parameters));
      $severity = ArcanistLintSeverity::SEVERITY_ADVICE;
      switch ($issue->Index->Severity) {
        case 0:
          $severity = ArcanistLintSeverity::SEVERITY_ADVICE;
          break;
        case 1:
          $severity = ArcanistLintSeverity::SEVERITY_AUTOFIX;
          break;
        case 2:
          $severity = ArcanistLintSeverity::SEVERITY_WARNING;
          break;
        case 3:
          $severity = ArcanistLintSeverity::SEVERITY_ERROR;
          break;
        case 4:
          $severity = ArcanistLintSeverity::SEVERITY_DISABLED;
          break;
      }
      $severity_override = $this->getLintMessageSeverity($issue->Index->Code);
      if ($severity_override !== null) {
        $severity = $severity_override;
      }
      $message->setSeverity($severity);
      $this->addLintMessage($message);
    }
  }

  protected function getDefaultMessageSeverity($code) {
    return null;
  }

}
