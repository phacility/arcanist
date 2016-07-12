<?php

/**
 * Shows lint messages to the user.
 */
final class ArcanistSummaryLintRenderer extends ArcanistLintRenderer {

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
    return phutil_console_format(
      "<bg:green>** %s **</bg> %s\n",
      pht('OKAY'),
      pht('No lint warnings.'));
  }

}
