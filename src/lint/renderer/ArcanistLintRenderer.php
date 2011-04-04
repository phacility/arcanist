<?php

/*
 * Copyright 2011 Facebook, Inc.
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
 * Shows lint messages to the user.
 *
 * @group lint
 */
class ArcanistLintRenderer {
  public function renderLintResult(ArcanistLintResult $result) {
    $messages = $result->getMessages();
    $path = $result->getPath();
    $lines = explode("\n", $result->getData());

    $text = array();
    $text[] = phutil_console_format('**>>>** Lint for __%s__:', $path);
    $text[] = null;
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
      $description = phutil_console_wrap($message->getDescription(), 4);

      $text[] = phutil_console_format(
        "  **<bg:{$color}> %s </bg>** (%s) __%s__\n".
        "    %s\n",
        $severity,
        $code,
        $name,
        $description);

      if ($message->hasFileContext()) {
        $text[] = $this->renderContext($message, $lines);
      }
    }
    $text[] = null;
    $text[] = null;

    return implode("\n", $text);
  }

  protected function renderContext(
    ArcanistLintMessage $message,
    array $line_data) {

    $lines_of_context = 3;
    $out = array();

    $line_num = min($message->getLine(), count($line_data));
    $line_num = max(1, $line_num);

    // Print out preceding context before the impacted region.
    $cursor = max(1, $line_num - $lines_of_context);
    for (; $cursor < $line_num; $cursor++) {
      $out[] = $this->renderLine($cursor, $line_data[$cursor - 1]);
    }

    // Print out the impacted region itself.
    $diff = $message->isPatchable() ? '-' : null;
    $text = $message->getOriginalText();
    $text_lines = explode("\n", $text);
    $text_length = count($text_lines);

    for (; $cursor < $line_num + $text_length; $cursor++) {
      $chevron = ($cursor == $line_num);
      // We may not have any data if, e.g., the old file does not exist.
      $data = idx($line_data, $cursor - 1, null);

      // Highlight the problem substring.
      $text_line = $text_lines[$cursor - $line_num];
      if (strlen($text_line)) {
        $data = substr_replace(
          $data,
          phutil_console_format('##%s##', $text_line),
          ($cursor == $line_num)
            ? $message->getChar() - 1
            : 0,
          strlen($text_line));
      }

      $out[] = $this->renderLine($cursor, $data, $chevron, $diff);
    }

    if ($message->isPatchable()) {
      $patch = $message->getReplacementText();
      $patch_lines = explode("\n", $patch);
      $offset = 0;
      foreach ($patch_lines as $patch_line) {
        if (isset($line_data[$line_num - 1 + $offset])) {
          $base = $line_data[$line_num - 1 + $offset];
        } else {
          $base = '';
        }

        if ($offset == 0) {
          $start = $message->getChar() - 1;
        } else {
          $start = 0;
        }

        if (isset($text_lines[$offset])) {
          $len = strlen($text_lines[$offset]);
        } else {
          $len = 0;
        }

        $patched = substr_replace(
          $base,
          phutil_console_format('##%s##', $patch_line),
          $start,
          $len);
        $out[] = $this->renderLine(null, $patched, false, '+');

        $offset++;
      }
    }

    $lines_count = count($line_data);
    $end = min($lines_count, $cursor + $lines_of_context);
    for (; $cursor < $end; $cursor++) {
      $out[] = $this->renderLine($cursor, $line_data[$cursor - 1]);
    }
    $out[] = null;

    return implode("\n", $out);
  }

  protected function renderLine($line, $data, $chevron = false, $diff = null) {
    $chevron = $chevron ? '>>>' : '';
    return sprintf(
      "    %3s %1s %6s %s",
      $chevron,
      $diff,
      $line,
      $data);
  }
}
