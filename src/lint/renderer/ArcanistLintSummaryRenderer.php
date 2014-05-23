<?php

/**
 * Shows lint messages to the user.
 *
 * @group lint
 */
final class ArcanistLintSummaryRenderer extends ArcanistLintRenderer {
  public function renderLintResult(ArcanistLintResult $result) {
    $messages = $result->getMessages();
    $path = $result->getPath();

    $text = array();
    foreach ($messages as $message) {
      $name = $message->getName();
      $severity = ArcanistLintSeverity::getStringForSeverity(
        $message->getSeverity());
      $line = $message->getLine();

      $text[] = "{$path}:{$line}:{$severity}: {$name}\n";
    }

    return implode('', $text);
  }

  public function renderOkayResult() {
    return
      phutil_console_format("<bg:green>** OKAY **</bg> No lint warnings.\n");
  }
}
