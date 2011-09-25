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

final class ArcanistMercurialParserTestCase extends ArcanistPhutilTestCase {

  public function testParseAll() {
    $root = dirname(__FILE__).'/data/';
    foreach (Filesystem::listDirectory($root, $hidden = false) as $file) {
      $this->parseData(
        basename($file),
        Filesystem::readFile($root.'/'.$file));
    }
  }

  private function parseData($name, $data) {
    switch ($name) {
      case 'branches-basic.txt':
        $output = ArcanistMercurialParser::parseMercurialBranches($data);
        $this->assertEqual(
          array('default', 'stable'),
          array_keys($output));
        $this->assertEqual(
          array('a21ccf4412d5', 'ec222a29bdf0'),
          array_values(ipull($output, 'rev')));
        break;
      case 'log-basic.txt':
        $output = ArcanistMercurialParser::parseMercurialLog($data);
        $this->assertEqual(
          3,
          count($output));
        $this->assertEqual(
          array('a21ccf4412d5', 'a051f8a6a7cc', 'b1f49efeab65'),
          array_values(ipull($output, 'rev')));
        break;
      case 'log-empty.txt':
        // Empty logs (e.g., "hg parents" for a root revision) should parse
        // correctly.
        $output = ArcanistMercurialParser::parseMercurialLog($data);
        $this->assertEqual(
          array(),
          $output);
        break;
      case 'status-basic.txt':
        $output = ArcanistMercurialParser::parseMercurialStatus($data);
        $this->assertEqual(
          4,
          count($output));
        $this->assertEqual(
          array('changed', 'added', 'removed', 'untracked'),
          array_keys($output));
        break;
      case 'status-moves.txt':
        $output = ArcanistMercurialParser::parseMercurialStatusDetails($data);
        $this->assertEqual(
          'move_source',
          $output['moved_file']['from']);
        $this->assertEqual(
          null,
          $output['changed_file']['from']);
        $this->assertEqual(
          'copy_source',
          $output['copied_file']['from']);
        $this->assertEqual(
          null,
          idx($output, 'copy_source'));
        break;
      default:
        throw new Exception("No test information for test data '{$name}'!");
    }
  }
}
