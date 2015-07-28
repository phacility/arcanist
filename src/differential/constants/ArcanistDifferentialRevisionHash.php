<?php

final class ArcanistDifferentialRevisionHash extends Phobject {

  const TABLE_NAME = 'differential_revisionhash';

  const HASH_GIT_COMMIT         = 'gtcm';
  const HASH_GIT_TREE           = 'gttr';
  const HASH_MERCURIAL_COMMIT   = 'hgcm';

  public static function getTypes() {
    return array(
      self::HASH_GIT_COMMIT,
      self::HASH_GIT_TREE,
      self::HASH_MERCURIAL_COMMIT,
    );
  }

}
