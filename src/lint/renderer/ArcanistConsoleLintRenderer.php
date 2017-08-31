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
    $data = $result->getData();

    $line_map = $this->newOffsetMap($data);

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
        $text[] = $this->renderContext($message, $data, $line_map);
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
      $old_impact = substr_count($original, "\n") + 1;
      $new_impact = substr_count($replacement, "\n") + 1;

      $start = $line;

      // If lines at the beginning of the changed line range are actually the
      // same, shrink the range. This happens when a patch just adds a line.
      do {
        if ($old_lines[$start - 1] != $new_lines[$start - 1]) {
          break;
        }

        $start++;
        $old_impact--;
        $new_impact--;

        if ($old_impact < 0 || $new_impact < 0) {
          throw new Exception(
            pht(
              'Modified prefix line range has become negative '.
              '(old = %d, new = %d).',
              $old_impact,
              $new_impact));
        }
      } while (true);

      // If the lines at the end of the changed line range are actually the
      // same, shrink the range. This happens when a patch just removes a
      // line.
      do {
        $old_suffix = $old_lines[$start + $old_impact - 2];
        $new_suffix = $new_lines[$start + $new_impact - 2];

        if ($old_suffix != $new_suffix) {
          break;
        }

        $old_impact--;
        $new_impact--;

        if ($old_impact < 0 || $new_impact < 0) {
          throw new Exception(
            pht(
              'Modified suffix line range has become negative '.
              '(old = %d, new = %d).',
              $old_impact,
              $new_impact));
        }
      } while (true);

    } else {
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
      $out[] = array(
        'text' => $new_lines[$ii - 1],
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

      $result[] = $this->renderLine(
        idx($spec, 'number'),
        $spec['text'],
        $chevron,
        idx($spec, 'type'));
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

  public function renderOkayResult() {
    return phutil_console_format(
      "<bg:green>** %s **</bg> %s\n",
      pht('OKAY'),
      pht('No lint warnings.'));
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

    return $line_map;
  }

}
