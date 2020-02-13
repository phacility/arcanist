<?php

final class ArcanistJSONLintRenderer extends ArcanistLintRenderer {

  const RENDERERKEY = 'json';

  const LINES_OF_CONTEXT = 3;

  public function renderLintResult(ArcanistLintResult $result) {
    $messages = $result->getMessages();
    $path = $result->getPath();
    $data = explode("\n", $result->getData());
    array_unshift($data, ''); // make the line numbers work as array indices

    $output = array($path => array());

    foreach ($messages as $message) {
      $dictionary = $message->toDictionary();
      $dictionary['context'] = implode("\n", array_slice(
        $data,
        max(1, $message->getLine() - self::LINES_OF_CONTEXT),
        self::LINES_OF_CONTEXT * 2 + 1));
      unset($dictionary['path']);
      $output[$path][] = $dictionary;
    }

    $this->writeOut(json_encode($output)."\n");
  }

}
