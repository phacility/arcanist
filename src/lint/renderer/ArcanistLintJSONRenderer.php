<?php

/*
 * Copyright 2012 Facebook, Inc.
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
final class ArcanistLintJSONRenderer {
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
          $message->getLine() - self::LINES_OF_CONTEXT,
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
