<?php

final class ArcanistSummaryLintRenderer extends ArcanistLintRenderer {

  const RENDERERKEY = 'summary';

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

    $this->writeOut(implode('', $text));
  }

  public function renderResultCode($result_code) {
    if ($result_code == ArcanistLintWorkflow::RESULT_OKAY) {
      $view = new PhutilConsoleInfo(
        pht('OKAY'),
        pht('No lint messages.'));
      $this->writeOut($view->drawConsoleString());
    }
  }

}
