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
 * Stifles creativity in choosing imaginative file names.
 *
 * @group linter
 */
final class ArcanistFilenameLinter extends ArcanistLinter {

  const LINT_BAD_FILENAME = 1;

  public function willLintPaths(array $paths) {
    return;
  }

  public function getLinterName() {
    return 'NAM';
  }

  public function getLintSeverityMap() {
    return array();
  }

  public function getLintNameMap() {
    return array(
      self::LINT_BAD_FILENAME   => 'Bad Filename',
    );
  }

  public function lintPath($path) {
    if (!preg_match('@^[a-z0-9./_-]+$@i', $path)) {
      $this->raiseLintAtPath(
        self::LINT_BAD_FILENAME,
        'Name files using only letters, numbers, period, hyphen and '.
        'underscore.');
    }
  }

}
