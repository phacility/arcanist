<?php

final class ArcanistMercurialParserTestCase extends PhutilTestCase {

  public function testParseAll() {
    $root = dirname(__FILE__).'/mercurial/';
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
      case 'branches-with-spaces.txt':
        $output = ArcanistMercurialParser::parseMercurialBranches($data);
        $this->assertEqual(
          array(
            'm m m m m 2:ffffffffffff (inactive)',
            'xxx yyy zzz',
            'default',
            "'",
          ),
          array_keys($output));
        $this->assertEqual(
          array('0b9d8290c4e0', '78963faacfc7', '5db03c5500c6', 'ffffffffffff'),
          array_values(ipull($output, 'rev')));
        break;
      case 'branches-empty.txt':
        $output = ArcanistMercurialParser::parseMercurialBranches($data);
        $this->assertEqual(array(), $output);
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
        throw new Exception(
          pht("No test information for test data '%s'!", $name));
    }
  }

}
