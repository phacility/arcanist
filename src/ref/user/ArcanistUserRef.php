<?php

final class ArcanistUserRef
  extends ArcanistRef {

  private $parameters;

  public function getRefDisplayName() {
    return pht('User "%s"', $this->getUsername());
  }

  public static function newFromConduit(array $parameters) {
    $ref = new self();
    $ref->parameters = $parameters;
    return $ref;
  }

  public static function newFromConduitWhoami(array $parameters) {
    // NOTE: The "user.whoami" call returns a different structure than
    // "user.search". Mangle the data so it looks similar.

    $parameters['fields'] = array(
      'username' => idx($parameters, 'userName'),
    );

    return self::newFromConduit($parameters);
  }

  public function getUsername() {
    return idxv($this->parameters, array('fields', 'username'));
  }

}
