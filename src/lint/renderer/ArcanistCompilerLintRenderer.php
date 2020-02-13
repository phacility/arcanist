<?php

final class ArcanistCompilerLintRenderer extends ArcanistLintRenderer {

  const RENDERERKEY = 'compiler';

  public function renderLintResult(ArcanistLintResult $result) {
    $lines = array();
    $messages = $result->getMessages();
    $path = $result->getPath();

    foreach ($messages as $message) {
      $severity = ArcanistLintSeverity::getStringForSeverity(
        $message->getSeverity());
      $line = $message->getLine();
      $code = $message->getCode();
      $description = $message->getDescription();
      $lines[] = sprintf(
        "%s:%d:%s (%s) %s\n",
        $path,
        $line,
        $severity,
        $code,
        $description);
    }

    $this->writeOut(implode('', $lines));
  }

}
