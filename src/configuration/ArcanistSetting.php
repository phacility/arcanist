<?php

abstract class ArcanistSetting
  extends Phobject {

  final public function getSettingKey() {
    return $this->getPhobjectClassConstant('SETTINGKEY', 32);
  }

  public function getAliases() {
    return array();
  }

  abstract public function getHelp();
  abstract public function getType();

  public function getExample() {
    return null;
  }

  final public function getLegacyDictionary() {
    $result = array(
      'type' => $this->getType(),
      'help' => $this->getHelp(),
    );

    $example = $this->getExample();
    if ($example !== null) {
      $result['example'] = $example;
    }

    $aliases = $this->getAliases();
    if ($aliases) {
      $result['legacy'] = head($aliases);
    }

    return $result;
  }

  final public static function getAllSettings() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getSettingKey')
      ->setSortMethod('getSettingKey')
      ->execute();
  }

}
