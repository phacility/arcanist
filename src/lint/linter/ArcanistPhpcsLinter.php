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
 * Uses "PHP_CodeSniffer" to detect checkstyle errors in php code.
 * To use this linter, you must install PHP_CodeSniffer.
 * http://pear.php.net/package/PHP_CodeSniffer.
 *
 * Optional configurations in .arcconfig:
 *
 *   lint.phpcs.standard
 *   lint.phpcs.options
 *   lint.phpcs.bin
 *
 * @group linter
 */
final class ArcanistPhpcsLinter extends ArcanistLinter {

  private $reports;
  private $stdout;

  public function getLinterName() {
    return 'PHPCS';
  }

  public function getLintSeverityMap() {
    return array();
  }

  public function getLintNameMap() {
    return array();
  }

  public function getPhpcsOptions() {
    $working_copy = $this->getEngine()->getWorkingCopy();

    $options = $working_copy->getConfig('lint.phpcs.options');

    $standard = $working_copy->getConfig('lint.phpcs.standard');
    $options .= !empty($standard) ? ' --standard=' . $standard : '';

    return $options;
  }

  private function getPhpcsPath() {
    $working_copy = $this->getEngine()->getWorkingCopy();
    $bin = $working_copy->getConfig('lint.phpcs.bin');

    if ($bin === null) {
      $bin = 'phpcs';
    }

    return $bin;
  }

  public function willLintPaths(array $paths) {
    $phpcs_bin = $this->getPhpcsPath();
    $phpcs_options = $this->getPhpcsOptions();
    $futures = array();

    foreach ($paths as $path) {
      $filepath = $this->getEngine()->getFilePathOnDisk($path);
      $this->reports[$path] = new TempFile();
      $futures[$path] = new ExecFuture('%C %C --report=xml --report-file=%s %s',
        $phpcs_bin,
        $phpcs_options,
        $this->reports[$path],
        $filepath);
    }

    foreach (Futures($futures)->limit(8) as $path => $future) {
      $this->results[$path] = $future->resolve();
    }
  }

  protected function loadXmlException() {
    throw new ArcanistUsageException('PHPCS Linter failed to load ' .
      'reporting file. Something happened when running phpcs. ' .
      "Output:\n$this->stdout" .
      "\nTry running lint with --trace flag to get more details.");
  }

  public function lintPath($path) {
    list($rc, $stdout) = $this->results[$path];

    $report = Filesystem::readFile($this->reports[$path]);
    $report_dom = new DOMDocument();

    // Unfortunately loadXML does not have normal error reporting,
    // so we need temporary to take over error handler
    set_error_handler(array($this, 'loadXmlException'));
    $this->stdout = $stdout;
    $report_dom->loadXML($report);
    restore_error_handler();

    $files = $report_dom->getElementsByTagName('file');
    foreach ($files as $file) {
      foreach ($file->childNodes as $child) {
        if (!($child instanceof DOMElement)) {
          continue;
        }

        $data = $this->getData($path);
        $lines = explode("\n", $data);
        $line = $lines[$child->getAttribute('line') - 1];
        $text = substr($line, $child->getAttribute('column') - 1);
        $name = $this->getLinterName() . ' - ' . $child->getAttribute('source');
        $severity = $child->tagName == 'error' ?
            ArcanistLintSeverity::SEVERITY_ERROR
            : ArcanistLintSeverity::SEVERITY_WARNING;

        $message = new ArcanistLintMessage();
        $message->setPath($path);
        $message->setLine($child->getAttribute('line'));
        $message->setChar($child->getAttribute('column'));
        $message->setCode($child->getAttribute('severity'));
        $message->setName($name);
        $message->setDescription($child->nodeValue);
        $message->setSeverity($severity);
        $message->setOriginalText($text);
        $this->addLintMessage($message);
      }
    }
  }
}
