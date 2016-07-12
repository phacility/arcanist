<?php

/**
 * Shows lint messages to the user.
 */
final class ArcanistConsoleLintRenderer extends ArcanistLintRenderer {

  private $showAutofixPatches = false;

  public function setShowAutofixPatches($show_autofix_patches) {
    $this->showAutofixPatches = $show_autofix_patches;
    return $this;
  }

  public function renderLintResult(ArcanistLintResult $result) {
    $messages = $result->getMessages();
    $path = $result->getPath();

    $lines = explode("\n", $result->getData());

    $text = array();

    foreach ($messages as $message) {
      if (!$this->showAutofixPatches && $message->isAutofix()) {
        continue;
      }

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
        $text[] = $this->renderContext($message, $lines);
      }
    }

    if ($text) {
      $prefix = phutil_console_format(
        "**>>>** %s\n\n\n",
        pht(
          'Lint for %s:',
          phutil_console_format('__%s__', $path)));
      return $prefix.implode("\n", $text);
    } else {
      return null;
    }
  }

  protected function renderContext(
    ArcanistLintMessage $message,
    array $line_data) {

    $lines_of_context = 3;
    $out = array();

    $num_lines = count($line_data);
     // make line numbers line up with array indexes
    array_unshift($line_data, '');

    $line_num = min($message->getLine(), $num_lines);
    $line_num = max(1, $line_num);

    // Print out preceding context before the impacted region.
    $cursor = max(1, $line_num - $lines_of_context);
    for (; $cursor < $line_num; $cursor++) {
      $out[] = $this->renderLine($cursor, $line_data[$cursor]);
    }

    $text = $message->getOriginalText();
    $start = $message->getChar() - 1;
    $patch = '';
    // Refine original and replacement text to eliminate start and end in common
    if ($message->isPatchable()) {
      $patch = $message->getReplacementText();
      $text_strlen = strlen($text);
      $patch_strlen = strlen($patch);
      $min_length = min($text_strlen, $patch_strlen);

      $same_at_front = 0;
      for ($ii = 0; $ii < $min_length; $ii++) {
        if ($text[$ii] !== $patch[$ii]) {
          break;
        }
        $same_at_front++;
        $start++;
        if ($text[$ii] == "\n") {
          $out[] = $this->renderLine($cursor, $line_data[$cursor]);
          $cursor++;
          $start = 0;
          $line_num++;
        }
      }
      // deal with shorter string '     ' longer string '     a     '
      $min_length -= $same_at_front;

      // And check the end of the string
      $same_at_end = 0;
      for ($ii = 1; $ii <= $min_length; $ii++) {
        if ($text[$text_strlen - $ii] !== $patch[$patch_strlen - $ii]) {
          break;
        }
        $same_at_end++;
      }

      $text = substr(
        $text,
        $same_at_front,
        $text_strlen - $same_at_end - $same_at_front);
      $patch = substr(
        $patch,
        $same_at_front,
        $patch_strlen - $same_at_end - $same_at_front);
    }
    // Print out the impacted region itself.
    $diff = $message->isPatchable() ? '-' : null;

    $text_lines = explode("\n", $text);
    $text_length = count($text_lines);

    $intraline = ($text != '' || $start || !preg_match('/\n$/', $patch));

    if ($intraline) {
      for (; $cursor < $line_num + $text_length; $cursor++) {
        $chevron = ($cursor == $line_num);
        // We may not have any data if, e.g., the old file does not exist.
        $data = idx($line_data, $cursor, null);

        // Highlight the problem substring.
        $text_line = $text_lines[$cursor - $line_num];
        if (strlen($text_line)) {
          $data = substr_replace(
            $data,
            phutil_console_format('##%s##', $text_line),
            ($cursor == $line_num ? ($start > 0 ? $start : null) : 0),
            strlen($text_line));
        }

        $out[] = $this->renderLine($cursor, $data, $chevron, $diff);
      }
    }

    // Print out replacement text.
    if ($message->isPatchable()) {
      // Strip trailing newlines, since "explode" will create an extra patch
      // line for these.
      if (strlen($patch) && ($patch[strlen($patch) - 1] === "\n")) {
        $patch = substr($patch, 0, -1);
      }
      $patch_lines = explode("\n", $patch);
      $patch_length = count($patch_lines);

      $patch_line = $patch_lines[0];

      $len = isset($text_lines[0]) ? strlen($text_lines[0]) : 0;

      $patched = phutil_console_format('##%s##', $patch_line);

      if ($intraline) {
        $patched = substr_replace(
          $line_data[$line_num],
          $patched,
          $start,
          $len);
      }

      $out[] = $this->renderLine(null, $patched, false, '+');

      foreach (array_slice($patch_lines, 1) as $patch_line) {
        $out[] = $this->renderLine(
          null,
          phutil_console_format('##%s##', $patch_line), false, '+');
      }
    }

    $end = min($num_lines, $cursor + $lines_of_context);
    for (; $cursor < $end; $cursor++) {
      // If there is no original text, we didn't print out a chevron or any
      // highlighted text above, so print it out here. This allows messages
      // which don't have any original/replacement information to still
      // render with indicator chevrons.
      if ($text || $message->isPatchable()) {
        $chevron = false;
      } else {
        $chevron = ($cursor == $line_num);
      }
      $out[] = $this->renderLine($cursor, $line_data[$cursor], $chevron);

      // With original text, we'll render the text highlighted above. If the
      // lint message only has a line/char offset there's nothing to
      // highlight, so print out a caret on the next line instead.
      if ($chevron && $message->getChar()) {
        $out[] = $this->renderCaret($message->getChar());
      }
    }
    $out[] = null;

    return implode("\n", $out);
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

  public function renderOkayResult() {
    return phutil_console_format(
      "<bg:green>** %s **</bg> %s\n",
      pht('OKAY'),
      pht('No lint warnings.'));
  }

}
