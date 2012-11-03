<?php

/**
 * Enforces basic text file rules.
 *
 * @group linter
 */
final class ArcanistTextLinter extends ArcanistLinter {

  const LINT_DOS_NEWLINE            = 1;
  const LINT_TAB_LITERAL            = 2;
  const LINT_LINE_WRAP              = 3;
  const LINT_EOF_NEWLINE            = 4;
  const LINT_BAD_CHARSET            = 5;
  const LINT_TRAILING_WHITESPACE    = 6;
  const LINT_NO_COMMIT              = 7;

  private $maxLineLength = 80;

  public function setMaxLineLength($new_length) {
    $this->maxLineLength = $new_length;
    return $this;
  }

  public function willLintPaths(array $paths) {
    return;
  }

  public function getLinterName() {
    return 'TXT';
  }

  public function getLintSeverityMap() {
    return array(
      self::LINT_LINE_WRAP => ArcanistLintSeverity::SEVERITY_WARNING,
      self::LINT_TRAILING_WHITESPACE => ArcanistLintSeverity::SEVERITY_AUTOFIX,
    );
  }

  public function getLintNameMap() {
    return array(
      self::LINT_DOS_NEWLINE          => 'DOS Newlines',
      self::LINT_TAB_LITERAL          => 'Tab Literal',
      self::LINT_LINE_WRAP            => 'Line Too Long',
      self::LINT_EOF_NEWLINE          => 'File Does Not End in Newline',
      self::LINT_BAD_CHARSET          => 'Bad Charset',
      self::LINT_TRAILING_WHITESPACE  => 'Trailing Whitespace',
      self::LINT_NO_COMMIT            => 'Explicit @no'.'commit',
    );
  }

  public function lintPath($path) {
    if (!strlen($this->getData($path))) {
      // If the file is empty, don't bother; particularly, don't require
      // the user to add a newline.
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

    if ($this->getEngine()->getCommitHookMode()) {
      $this->lintNoCommit($path);
    }
  }

  protected function lintNewlines($path) {
    $pos = strpos($this->getData($path), "\r");
    if ($pos !== false) {
      $this->raiseLintAtOffset(
        $pos,
        self::LINT_DOS_NEWLINE,
        'You must use ONLY Unix linebreaks ("\n") in source code.',
        "\r");
      if ($this->isMessageEnabled(self::LINT_DOS_NEWLINE)) {
        $this->stopAllLinters();
      }
    }
  }

  protected function lintTabs($path) {
    $pos = strpos($this->getData($path), "\t");
    if ($pos !== false) {
      $this->raiseLintAtOffset(
        $pos,
        self::LINT_TAB_LITERAL,
        'Configure your editor to use spaces for indentation.',
        "\t");
    }
  }

  protected function lintLineLength($path) {
    $lines = explode("\n", $this->getData($path));

    $width = $this->maxLineLength;
    foreach ($lines as $line_idx => $line) {
      if (strlen($line) > $width) {
        $this->raiseLintAtLine(
          $line_idx + 1,
          1,
          self::LINT_LINE_WRAP,
          'This line is '.number_format(strlen($line)).' characters long, '.
          'but the convention is '.$width.' characters.',
          $line);
      }
    }
  }

  protected function lintEOFNewline($path) {
    $data = $this->getData($path);
    if (!strlen($data) || $data[strlen($data) - 1] != "\n") {
      $this->raiseLintAtOffset(
        strlen($data),
        self::LINT_EOF_NEWLINE,
        "Files must end in a newline.",
        '',
        "\n");
    }
  }

  protected function lintCharset($path) {
    $data = $this->getData($path);

    $matches = null;
    $preg = preg_match_all(
      '/[^\x09\x0A\x20-\x7E]+/',
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
        'Source code should contain only ASCII bytes with ordinal decimal '.
        'values between 32 and 126 inclusive, plus linefeed. Do not use UTF-8 '.
        'or other multibyte charsets.',
        $string);
    }

    if ($this->isMessageEnabled(self::LINT_BAD_CHARSET)) {
      $this->stopAllLinters();
    }
  }

  protected function lintTrailingWhitespace($path) {
    $data = $this->getData($path);

    $matches = null;
    $preg = preg_match_all(
      '/ +$/m',
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
        'This line contains trailing whitespace. Consider setting up your '.
          'editor to automatically remove trailing whitespace, you will save '.
          'time.',
        $string,
        '');
    }
  }

  private function lintNoCommit($path) {
    $data = $this->getData($path);

    $deadly = '@no'.'commit';

    $offset = strpos($data, $deadly);
    if ($offset !== false) {
      $this->raiseLintAtOffset(
        $offset,
        self::LINT_NO_COMMIT,
        'This file is explicitly marked as "'.$deadly.'", which blocks '.
        'commits.',
        $deadly);
    }
  }


}
