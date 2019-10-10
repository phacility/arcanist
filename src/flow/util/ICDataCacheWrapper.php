<?php

final class ICDataCacheWrapper extends PhutilKeyValueCacheProxy {

  private $hashFunction = 'crc32b';
  private $serializeCallback = 'serialize';
  private $unserializeCallback = 'unserialize';

  public function setHashFunction($function) {
    $this->hashFunction = $function;
    return $this;
  }

  public function setSerializeCallback($serialize) {
    $this->serializeCallback = $serialize;
    return $this;
  }

  public function setUnserializeCallback($unserialize) {
    $this->unserializeCallback = $unserialize;
    return $this;
  }

  private function getShortKey($key) {
    return hash($this->hashFunction, $key);
  }

  private function getShortKeyMap(array $keys) {
    $map = array();
    foreach ($keys as $key) {
      $map[$this->getShortKey($key)] = $key;
    }
    return $map;
  }

  public function getKeys(array $keys) {
    $map = $this->getShortKeyMap($keys);
    $values = array();
    foreach ($this->getProxy()->getKeys(array_keys($map)) as $key => $value) {
      $values[$map[$key]] = call_user_func($this->unserializeCallback, $value);
    }
    return $values;
  }


  public function setKeys(array $keys, $ttl = null) {
    $values = array();
    foreach ($keys as $key => $value) {
      $values[$this->getShortKey($key)] = call_user_func(
        $this->serializeCallback,
        $value);
    }
    $this->getProxy()->setKeys($values, $ttl);
  }


  public function deleteKeys(array $keys) {
    return $this->getProxy()->deleteKeys(
      array_keys($this->getShortKeyMap($keys)));
  }

}
