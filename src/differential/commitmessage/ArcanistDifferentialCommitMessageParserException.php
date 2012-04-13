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
 * Thrown when a commit message isn't parseable.
 *
 * @group differential
 */
final class ArcanistDifferentialCommitMessageParserException extends Exception {

  private $parserErrors;

  public function __construct(array $errors) {
    $this->parserErrors = $errors;
    parent::__construct(head($errors));
  }

  public function getParserErrors() {
    return $this->parserErrors;
  }

}
