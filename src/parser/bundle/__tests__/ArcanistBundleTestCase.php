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

final class ArcanistBundleTestCase extends ArcanistPhutilTestCase {

  private function loadResource($name) {
    return Filesystem::readFile($this->getResourcePath($name));
  }

  private function getResourcePath($name) {
    return dirname(__FILE__).'/data/'.$name;
  }

  private function loadDiff($old, $new) {
    list($err, $stdout) = exec_manual(
      'diff --unified=65535 --label %s --label %s -- %s %s',
      'file 9999-99-99',
      'file 9999-99-99',
      $this->getResourcePath($old),
      $this->getResourcePath($new));
    $this->assertEqual(
      1,
      $err,
      "Expect `diff` to find changes between '{$old}' and '{$new}'.");
    return $stdout;
  }

  private function loadOneChangeBundle($old, $new) {
    $diff = $this->loadDiff($old, $new);
    return ArcanistBundle::newFromDiff($diff);
  }

  public function testTrailingContext() {
    // Diffs need to generate without extra trailing context, or 'patch' will
    // choke on them.
    $this->assertEqual(
      $this->loadResource('trailing-context.diff'),
      $this->loadOneChangeBundle(
        'trailing-context.old',
        'trailing-context.new')->toUnifiedDiff());
  }

  public function testDisjointHunks() {
    // Diffs need to generate without overlapping hunks.
    $this->assertEqual(
      $this->loadResource('disjoint-hunks.diff'),
      $this->loadOneChangeBundle(
        'disjoint-hunks.old',
        'disjoint-hunks.new')->toUnifiedDiff());
  }




}
