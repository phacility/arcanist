<?php

final class PhutilJSONTestCase extends PhutilTestCase {

  public function testEmptyArrayEncoding() {
    $serializer = new PhutilJSON();

    $expect = <<<EOJSON
{
  "x": []
}

EOJSON;

    $this->assertEqual(
      $expect,
      $serializer->encodeFormatted(array('x' => array())),
      pht('Empty arrays should serialize as `%s`, not `%s`.', '[]', '{}'));
  }

  public function testNestedObjectEncoding() {
    $expect = <<<EOJSON
{
  "empty-object": {},
  "pair-object": {
    "duck": "quack"
  }
}

EOJSON;

    $empty_object = new stdClass();

    $pair_object = new stdClass();
    $pair_object->duck = 'quack';

    $input = (object)array(
      'empty-object' => $empty_object,
      'pair-object' => $pair_object,
    );

    $serializer = new PhutilJSON();

    $this->assertEqual(
      $expect,
      $serializer->encodeFormatted($input),
      pht('Serialization of PHP-object JSON values.'));
  }

}
