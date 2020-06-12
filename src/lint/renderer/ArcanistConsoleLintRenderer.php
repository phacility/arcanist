<?php

final class ArcanistConsoleLintRenderer extends ArcanistLintRenderer {

  const RENDERERKEY = 'console';

  private $testableMode;

  public function setTestableMode($testable_mode) {
    $this->testableMode = $testable_mode;
    return $this;
  }

  public function getTestableMode() {
    return $this->testableMode;
  }

  public function supportsPatching() {
    return true;
  }

  public function renderResultCode($result_code) {
    if ($result_code == ArcanistLintWorkflow::RESULT_OKAY) {
      $view = new PhutilConsoleInfo(
        pht('OKAY'),
        pht('No lint messages.'));
      $this->writeOut($view->drawConsoleString());
    }
  }

  public function promptForPatch(
    ArcanistLintResult $result,
    $old_path,
    $new_path) {

    if ($old_path === null) {
      $old_path = '/dev/null';
    }

    list($err, $stdout) = exec_manual('diff -u %s %s', $old_path, $new_path);
    $this->writeOut($stdout);

    $prompt = pht(
      'Apply this patch to %s?',
      tsprintf('__%s__', $result->getPath()));

    return phutil_console_confirm($prompt, $default_no = false);
  }

  public function renderLintResult(ArcanistLintResult $result) {
    $messages = $result->getMessages();
    $path = $result->getPath();
    $data = $result->getData();

    $line_map = $this->newOffsetMap($data);

    $text = array();
    foreach ($messages as $message) {
      if ($message->isError()) {
        $color = 'red';
      } else {
        $color = 'yellow';
      }

      $severity = ArcanistLintSeverity::getStringForSeverity(
        $message->getSeverity());
      $code = $message->getCode();
      $name = $message->getName();
      $description = $message->getDescription();

      if ($message->getOtherLocations()) {
        $locations = array();
        foreach ($message->getOtherLocations() as $location) {
          $locations[] =
            idx($location, 'path', $path).
            (!empty($location['line']) ? ":{$location['line']}" : '');
        }
        $description .= "\n".pht(
          'Other locations: %s',
          implode(', ', $locations));
      }

      $text[] = phutil_console_format(
        "  **<bg:{$color}> %s </bg>** (%s) __%s__\n%s\n",
        $severity,
        $code,
        $name,
        phutil_console_wrap($description, 4));

      if ($message->hasFileContext()) {
        $text[] = $this->renderContext($message, $data, $line_map);
      }
    }

    if ($text) {
      $prefix = phutil_console_format(
        "**>>>** %s\n\n\n",
        pht(
          'Lint for %s:',
          phutil_console_format('__%s__', $path)));
      $this->writeOut($prefix.implode("\n", $text));
    }
  }

  protected function renderContext(
    ArcanistLintMessage $message,
    $data,
    array $line_map) {

    $context = 3;

    $message = $message->newTrimmedMessage();

    $original = $message->getOriginalText();
    $replacement = $message->getReplacementText();

    $line = $message->getLine();
    $char = $message->getChar();

    $old = $data;
    $old_lines = phutil_split_lines($old);
    $old_impact = substr_count($original, "\n") + 1;
    $start = $line;

    // See PHI1782. If a linter raises a message at a line that does not
    // exist, just render a warning message.

    // Linters are permitted to raise a warning at the very end of a file.
    // For example, if a file is 13 lines long, it is valid to raise a message
    // on line 14 as long as the character position is 1 or unspecified and
    // there is no "original" text.

    $max_old = count($old_lines);

    $invalid_position = false;
    if ($start > ($max_old + 1)) {
      $invalid_position = true;
    } else if ($start > $max_old) {
      if (strlen($original)) {
        $invalid_position = true;
      } else if ($char !== null && $char !== 1) {
        $invalid_position = true;
      }
    }

    if ($invalid_position) {
      $warning = $this->renderLine(
        $start,
        pht(
          '(This message was raised at line %s, but the file only has '.
          '%s line(s).)',
          new PhutilNumber($start),
          new PhutilNumber($max_old)),
        false,
        '?');

      return $warning."\n\n";
    }

    if ($message->isPatchable()) {
      $patch_offset = $line_map[$line] + ($char - 1);

      $new = substr_replace(
        $old,
        $replacement,
        $patch_offset,
        strlen($original));
      $new_lines = phutil_split_lines($new);

      // Figure out how many "-" and "+" lines we have by counting the newlines
      // for the relevant patches. This may overestimate things if we are adding
      // or removing entire lines, but we'll adjust things below.
      $new_impact = substr_count($replacement, "\n") + 1;

      // If this is a change on a single line, we'll try to highlight the
      // changed character range to make it easier to pick out.
      if ($old_impact === 1 && $new_impact === 1) {
        $old_lines[$start - 1] = substr_replace(
          $old_lines[$start - 1],
          $this->highlightText($original),
          $char - 1,
          strlen($original));

        // See T13543. The message may have completely removed this line: for
        // example, if it trimmed trailing spaces from the end of a file. If
        // the line no longer exists, don't try to highlight it.
        if (isset($new_lines[$start - 1])) {
          $new_lines[$start - 1] = substr_replace(
            $new_lines[$start - 1],
            $this->highlightText($replacement),
            $char - 1,
            strlen($replacement));
        }
      }

      // If lines at the beginning of the changed line range are actually the
      // same, shrink the range. This happens when a patch just adds a line.
      do {
        $old_line = idx($old_lines, $start - 1, null);
        $new_line = idx($new_lines, $start - 1, null);

        if ($old_line !== $new_line) {
          break;
        }

        $start++;
        $old_impact--;
        $new_impact--;

        // We can end up here if a patch removes a line which occurs before
        // another identical line.
        if ($old_impact <= 0 || $new_impact <= 0) {
          break;
        }
      } while (true);

      // If the lines at the end of the changed line range are actually the
      // same, shrink the range. This happens when a patch just removes a
      // line.
      if ($old_impact > 0 && $new_impact > 0) {
        do {
          $old_suffix = idx($old_lines, $start + $old_impact - 2, null);
          $new_suffix = idx($new_lines, $start + $new_impact - 2, null);

          if ($old_suffix !== $new_suffix) {
            break;
          }

          $old_impact--;
          $new_impact--;

          // We can end up here if a patch removes a line which occurs after
          // another identical line.
          if ($old_impact <= 0 || $new_impact <= 0) {
            break;
          }
        } while (true);
      }

    } else {

      // If we have "original" text and it is contained on a single line,
      // highlight the affected area. If we don't have any text, we'll mark
      // the character with a caret (below, in rendering) instead.
      if ($old_impact == 1 && strlen($original)) {
        $old_lines[$start - 1] = substr_replace(
          $old_lines[$start - 1],
          $this->highlightText($original),
          $char - 1,
          strlen($original));
      }

      $old_impact = 0;
      $new_impact = 0;
    }

    $out = array();

    $head = max(1, $start - $context);
    for ($ii = $head; $ii < $start; $ii++) {
      $out[] = array(
        'text' => $old_lines[$ii - 1],
        'number' => $ii,
      );
    }

    for ($ii = $start; $ii < $start + $old_impact; $ii++) {
      $out[] = array(
        'text' => $old_lines[$ii - 1],
        'number' => $ii,
        'type' => '-',
        'chevron' => ($ii == $start),
      );
    }

    for ($ii = $start; $ii < $start + $new_impact; $ii++) {
      // If the patch was at the end of the file and ends with a newline, we
      // won't have an actual entry in the array for the last line, even though
      // we want to show it in the diff.
      $out[] = array(
        'text' => idx($new_lines, $ii - 1, ''),
        'type' => '+',
        'chevron' => ($ii == $start),
      );
    }

    $cursor = $start + $old_impact;
    $foot = min(count($old_lines), $cursor + $context);
    for ($ii = $cursor; $ii <= $foot; $ii++) {
      $out[] = array(
        'text' => $old_lines[$ii - 1],
        'number' => $ii,
        'chevron' => ($ii == $cursor),
      );
    }

    $result = array();

    $seen_chevron = false;
    foreach ($out as $spec) {
      if ($seen_chevron) {
        $chevron = false;
      } else {
        $chevron = !empty($spec['chevron']);
        if ($chevron) {
          $seen_chevron = true;
        }
      }

      // If the line doesn't actually end in a newline, add one so the layout
      // doesn't mess up. This can happen when the last line of the old file
      // didn't have a newline at the end.
      $text = $spec['text'];
      if (!preg_match('/\n\z/', $spec['text'])) {
        $text .= "\n";
      }

      $result[] = $this->renderLine(
        idx($spec, 'number'),
        $text,
        $chevron,
        idx($spec, 'type'));

      // If this is just a message and does not have a patch, put a little
      // caret underneath the line to point out where the issue is.
      if ($chevron) {
        if (!$message->isPatchable() && !strlen($original)) {
          $result[] = $this->renderCaret($char)."\n";
        }
      }
    }

    return implode('', $result);
  }

  private function renderCaret($pos) {
    return str_repeat(' ', 16 + $pos).'^';
  }

  protected function renderLine($line, $data, $chevron = false, $diff = null) {
    $chevron = $chevron ? '>>>' : '';
    return sprintf(
      '    %3s %1s %6s %s',
      $chevron,
      $diff,
      $line,
      $data);
  }

  private function newOffsetMap($data) {
    $lines = phutil_split_lines($data);

    $line_map = array();

    $number = 1;
    $offset = 0;
    foreach ($lines as $line) {
      $line_map[$number] = $offset;
      $number++;
      $offset += strlen($line);
    }

    // If the last line ends in a newline, add a virtual offset for the final
    // line with no characters on it. This allows lint messages to target the
    // last line of the file at character 1.
    if ($lines) {
      if (preg_match('/\n\z/', $line)) {
        $line_map[$number] = $offset;
      }
    }

    return $line_map;
  }

  private function highlightText($text) {
    if ($this->getTestableMode()) {
      return '>'.$text.'<';
    } else {
      return (string)tsprintf('##%s##', $text);
    }
  }

}
