<?php

/*
 * Copyright 2011 Facebook, Inc.
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

function xhpast_get_parser_future($data) {
  static $bin_path;
  if (empty($bin_path)) {
    $root = dirname(__FILE__);
    $bin_path = $root.'/xhpast';
  }

  if (!file_exists($bin_path)) {
    execx(
      '(cd %s && make && make install)',
      dirname(__FILE__).'/../../../../../support/xhpast');
  }

  $future = new ExecFuture($bin_path);
  $future->write($data);

  return $future;
}
