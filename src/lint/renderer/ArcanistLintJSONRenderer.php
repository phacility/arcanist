<?php

/**
 * Shows lint messages to the user.
 *
 * @group lint
 */
final class ArcanistLintJSONRenderer implements ArcanistLintRenderer {
  const LINES_OF_CONTEXT = 3;

  public function renderLintResult(ArcanistLintResult $result) {
    $messages = $result->getMessages();
    $path = $result->getPath();
    $data = explode("\n", $result->getData());
    array_unshift($data, ''); // make the line numbers work as array indices

    $output = array($path => array());

    foreach ($messages as $message) {
      $output[$path][] = array(
        'code' => $message->getCode(),
        'name' => $message->getName(),
        'severity' => $message->getSeverity(),
        'line' => $message->getLine(),
        'char' => $message->getChar(),
        'context' => implode("\n", array_slice(
          $data,
          max(1, $message->getLine() - self::LINES_OF_CONTEXT),
          self::LINES_OF_CONTEXT * 2 + 1
        )),
        'description' => $message->getDescription(),
      );
    }

    return json_encode($output)."\n";
  }

  public function renderOkayResult() {
    return "";
  }
}
