<?php

/**
 * Enforces basic text file rules.
 */
final class ArcanistTextLinter extends ArcanistLinter {

  const LINT_DOS_NEWLINE          = 1;
  const LINT_TAB_LITERAL          = 2;
  const LINT_LINE_WRAP            = 3;
  const LINT_EOF_NEWLINE          = 4;
  const LINT_BAD_CHARSET          = 5;
  const LINT_TRAILING_WHITESPACE  = 6;
  const LINT_BOF_WHITESPACE       = 8;
  const LINT_EOF_WHITESPACE       = 9;
  const LINT_EMPTY_FILE           = 10;

  private $maxLineLength = 80;

  public function getInfoName() {
    return pht('Basic Text Linter');
  }

  public function getInfoDescription() {
    return pht(
      'Enforces basic text rules like line length, character encoding, '.
      'and trailing whitespace.');
  }

  public function getLinterPriority() {
    return 0.5;
  }

  public function getLinterConfigurationOptions() {
    $options = array(
      'text.max-line-length' => array(
        'type' => 'optional int',
        'help' => pht(
          'Adjust the maximum line length before a warning is raised. By '.
          'default, a warning is raised on lines exceeding 80 characters.'),
      ),
    );

    return $options + parent::getLinterConfigurationOptions();
  }

  public function setMaxLineLength($new_length) {
    $this->maxLineLength = $new_length;
    return $this;
  }

  public function setLinterConfigurationValue($key, $value) {
    switch ($key) {
      case 'text.max-line-length':
        $this->setMaxLineLength($value);
        return;
    }

    return parent::setLinterConfigurationValue($key, $value);
  }

  public function getLinterName() {
    return 'TXT';
  }

  public function getLinterConfigurationName() {
    return 'text';
  }

  public function getLintSeverityMap() {
    return array(
      self::LINT_LINE_WRAP           => ArcanistLintSeverity::SEVERITY_WARNING,
      self::LINT_TRAILING_WHITESPACE => ArcanistLintSeverity::SEVERITY_AUTOFIX,
      self::LINT_BOF_WHITESPACE      => ArcanistLintSeverity::SEVERITY_AUTOFIX,
      self::LINT_EOF_WHITESPACE      => ArcanistLintSeverity::SEVERITY_AUTOFIX,
    );
  }

  public function getLintNameMap() {
    return array(
      self::LINT_DOS_NEWLINE         => pht('DOS Newlines'),
      self::LINT_TAB_LITERAL         => pht('Tab Literal'),
      self::LINT_LINE_WRAP           => pht('Line Too Long'),
      self::LINT_EOF_NEWLINE         => pht('File Does Not End in Newline'),
      self::LINT_BAD_CHARSET         => pht('Bad Charset'),
      self::LINT_TRAILING_WHITESPACE => pht('Trailing Whitespace'),
      self::LINT_BOF_WHITESPACE      => pht('Leading Whitespace at BOF'),
      self::LINT_EOF_WHITESPACE      => pht('Trailing Whitespace at EOF'),
      self::LINT_EMPTY_FILE          => pht('Empty File'),
    );
  }

  protected function lintPath(ArcanistWorkingCopyPath $path) {
    $this->lintEmptyFile($path);

    $data = $path->getData();
    if (!strlen($data)) {
      // If the file is empty, don't bother; particularly, don't require
      // the user to add a newline.
      return;
    }

    if ($this->didStopAllLinters()) {
      return;
    }

    $this->lintNewlines($path);
    $this->lintTabs($path);

    if ($this->didStopAllLinters()) {
      return;
    }

    $this->lintCharset($path);

    if ($this->didStopAllLinters()) {
      return;
    }

    $this->lintLineLength($path);
    $this->lintEOFNewline($path);
    $this->lintTrailingWhitespace($path);

    $this->lintBOFWhitespace($path);
    $this->lintEOFWhitespace($path);
  }

  private function lintEmptyFile(ArcanistWorkingCopyPath $path) {
    // If this file has any content, it isn't empty.
    $data = $path->getData();
    if (!preg_match('/^\s*$/', $data)) {
      return;
    }

    // It is reasonable for certain file types to be completely empty,
    // so they are excluded here.
    $basename = $path->getBasename();

    // Allow empty "__init__.py", as this is legitimate in Python.
    if ($basename === '__init__.py') {
      return;
    }

    // Allow empty ".gitkeep" and similar files.
    if (isset($filename[0]) && $filename[0] == '.') {
      return;
    }

    $this->raiseLintAtPath(
      self::LINT_EMPTY_FILE,
      pht('Empty files usually do not serve any useful purpose.'));

    $this->stopAllLinters();
  }

  private function lintNewlines(ArcanistWorkingCopyPath $path) {
    $data = $path->getData();
    $pos = strpos($data, "\r");

    if ($pos !== false) {
      $this->raiseLintAtOffset(
        0,
        self::LINT_DOS_NEWLINE,
        pht('You must use ONLY Unix linebreaks ("%s") in source code.', '\n'),
        $data,
        str_replace("\r\n", "\n", $data));

      if ($this->isMessageEnabled(self::LINT_DOS_NEWLINE)) {
        $this->stopAllLinters();
      }
    }
  }

  private function lintTabs(ArcanistWorkingCopyPath $path) {
    $data = $path->getData();
    $pos = strpos($data, "\t");

    if ($pos !== false) {
      $this->raiseLintAtOffset(
        $pos,
        self::LINT_TAB_LITERAL,
        pht('Configure your editor to use spaces for indentation.'),
        "\t");
    }
  }

  private function lintLineLength(ArcanistWorkingCopyPath $path) {
    $lines = $path->getDataAsLines();

    $width = $this->maxLineLength;
    foreach ($lines as $line_idx => $line) {
      $line = rtrim($line, "\n");
      if (strlen($line) > $width) {
        $this->raiseLintAtLine(
          $line_idx + 1,
          1,
          self::LINT_LINE_WRAP,
          pht(
            'This line is %s characters long, but the '.
            'convention is %s characters.',
            new PhutilNumber(strlen($line)),
            $width),
          $line);
      }
    }
  }

  private function lintEOFNewline(ArcanistWorkingCopyPath $path) {
    $data = $path->getData();

    if (!strlen($data) || $data[strlen($data) - 1] != "\n") {
      $this->raiseLintAtOffset(
        strlen($data),
        self::LINT_EOF_NEWLINE,
        pht('Files must end in a newline.'),
        '',
        "\n");
    }
  }

  private function lintCharset(ArcanistWorkingCopyPath $path) {
    $data = $path->getData();

    $matches = null;
    $bad = '[^\x09\x0A\x20-\x7E]';
    $preg = preg_match_all(
      "/{$bad}(.*{$bad})?/",
      $data,
      $matches,
      PREG_OFFSET_CAPTURE);

    if (!$preg) {
      return;
    }

    foreach ($matches[0] as $match) {
      list($string, $offset) = $match;
      $this->raiseLintAtOffset(
        $offset,
        self::LINT_BAD_CHARSET,
        pht(
          'Source code should contain only ASCII bytes with ordinal '.
          'decimal values between 32 and 126 inclusive, plus linefeed. '.
          'Do not use UTF-8 or other multibyte charsets.'),
        $string);
    }

    if ($this->isMessageEnabled(self::LINT_BAD_CHARSET)) {
      $this->stopAllLinters();
    }
  }

  private function lintTrailingWhitespace(ArcanistWorkingCopyPath $path) {
    $data = $path->getData();

    $matches = null;
    $preg = preg_match_all(
      '/[[:blank:]]+$/m',
      $data,
      $matches,
      PREG_OFFSET_CAPTURE);

    if (!$preg) {
      return;
    }

    foreach ($matches[0] as $match) {
      list($string, $offset) = $match;
      $this->raiseLintAtOffset(
        $offset,
        self::LINT_TRAILING_WHITESPACE,
        pht(
          'This line contains trailing whitespace. Consider setting '.
          'up your editor to automatically remove trailing whitespace, '.
          'you will save time.'),
        $string,
        '');
    }
  }

  private function lintBOFWhitespace(ArcanistWorkingCopyPath $path) {
    $data = $path->getData();

    $matches = null;
    $preg = preg_match(
      '/^\s*\n/',
      $data,
      $matches,
      PREG_OFFSET_CAPTURE);

    if (!$preg) {
      return;
    }

    list($string, $offset) = $matches[0];
    $this->raiseLintAtOffset(
      $offset,
      self::LINT_BOF_WHITESPACE,
      pht(
        'This file contains leading whitespace at the beginning of the file. '.
        'This is unnecessary and should be avoided when possible.'),
      $string,
      '');
  }

  private function lintEOFWhitespace(ArcanistWorkingCopyPath $path) {
    $data = $path->getData();

    $matches = null;
    $preg = preg_match(
      '/(?<=\n)\s+$/',
      $data,
      $matches,
      PREG_OFFSET_CAPTURE);

    if (!$preg) {
      return;
    }

    list($string, $offset) = $matches[0];
    $this->raiseLintAtOffset(
      $offset,
      self::LINT_EOF_WHITESPACE,
      pht('This file contains unnecessary trailing whitespace.'),
      $string,
      '');
  }

}
