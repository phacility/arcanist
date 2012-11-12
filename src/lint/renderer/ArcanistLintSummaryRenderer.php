<?php

/**
 * Shows lint messages to the user.
 *
 * @group lint
 */
final class ArcanistLintSummaryRenderer implements ArcanistLintRenderer {
  public function renderLintResult(ArcanistLintResult $result) {
    $messages = $result->getMessages();
    $path = $result->getPath();

    $text = array();
    $text[] = $path.":";
    foreach ($messages as $message) {
      $name = $message->getName();
      $severity = ArcanistLintSeverity::getStringForSeverity(
        $message->getSeverity());
      $line = $message->getLine();

      $text[] = "    {$severity} on line {$line}: {$name}";
    }
    $text[] = null;

    return implode("\n", $text);
  }

  public function renderOkayResult() {
    return
      phutil_console_format("<bg:green>** OKAY **</bg> No lint warnings.\n");
  }
}
