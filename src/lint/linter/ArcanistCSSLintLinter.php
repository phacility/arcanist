<?php

/**
 * Uses "CSS lint" to detect checkstyle errors in css code.
 * To use this linter, you must install CSS lint.
 * ##npm install csslint -g## (don't forget the -g flag or NPM will install
 * the package locally).
 *
 * Based on ArcanistPhpcsLinter.php
 *
 *   lint.cssling.options
 *   lint.csslint.bin
 *
 * @group linter
 */
final class ArcanistCSSLintLinter extends ArcanistLinter {

  private $reports;

  public function getLinterName() {
    return 'CSSLint';
  }

  public function getLintSeverityMap() {
    return array();
  }

  public function getLintNameMap() {
    return array();
  }

  public function getCSSLintOptions() {
    $working_copy = $this->getEngine()->getWorkingCopy();

    $options = $working_copy->getConfig('lint.csslint.options');

    return $options;
  }

  private function getCSSLintPath() {
    $working_copy = $this->getEngine()->getWorkingCopy();
    $bin = $working_copy->getConfig('lint.csslint.bin');

    if ($bin === null) {
      $bin = 'csslint';
    }

    return $bin;
  }

  public function willLintPaths(array $paths) {
    $csslint_bin = $this->getCSSLintPath();
    $csslint_options = $this->getCSSLintOptions();
    $futures = array();

    foreach ($paths as $path) {
      $filepath = $this->getEngine()->getFilePathOnDisk($path);
      $this->reports[$path] = new TempFile();
      $futures[$path] = new ExecFuture('%C %C --format=lint-xml >%s %s',
        $csslint_bin,
        $csslint_options,
        $this->reports[$path],
        $filepath);
    }

    foreach (Futures($futures)->limit(8) as $path => $future) {
      $this->results[$path] = $future->resolve();
    }

    libxml_use_internal_errors(true);
  }

  public function lintPath($path) {
    list($rc, $stdout) = $this->results[$path];

    $report = Filesystem::readFile($this->reports[$path]);

    if ($report) {
      $report_dom = new DOMDocument();
      libxml_clear_errors();
      $report_dom->loadXML($report);
    }
    if (!$report || libxml_get_errors()) {
      throw new ArcanistUsageException('CSS Linter failed to load ' .
        'reporting file. Something happened when running csslint. ' .
        "Output:\n$stdout" .
        "\nTry running lint with --trace flag to get more details.");
    }

    $files = $report_dom->getElementsByTagName('file');
    foreach ($files as $file) {
      foreach ($file->childNodes as $child) {
        if (!($child instanceof DOMElement)) {
          continue;
        }

        $data = $this->getData($path);
        $lines = explode("\n", $data);
        $name = $this->getLinterName() . ' - ' . $child->getAttribute('reason');
        $severity = $child->getAttribute('severity') == 'warning' ?
            ArcanistLintSeverity::SEVERITY_WARNING
            : ArcanistLintSeverity::SEVERITY_ERROR;

        $message = new ArcanistLintMessage();
        $message->setPath($path);
        $message->setLine($child->getAttribute('line'));
        $message->setChar($child->getAttribute('char'));
        $message->setCode($child->getAttribute('severity'));
        $message->setName($name);
        $message->setDescription($child->getAttribute('reason')."\nEvidence:".$child->getAttribute('evidence'));
        $message->setSeverity($severity);

        if($child->hasAttribute('line')){
            $line = $lines[$child->getAttribute('line') - 1];
            $text = substr($line, $child->getAttribute('char') - 1);
            $message->setOriginalText($text);
        }
        $this->addLintMessage($message);
      }
    }
  }
}
