<?php

final class ArcanistRepositoryAPIMiscTestCase extends PhutilTestCase {

  public function testSVNFileEscapes() {
    $input = array(
      '.',
      'x',
      'x@2x.png',
    );

    $expect = array(
      '.',
      'x',
      'x@2x.png@',
    );

    $this->assertEqual(
      $expect,
      ArcanistSubversionAPI::escapeFileNamesForSVN($input));
  }

}
