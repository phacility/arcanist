<?php

final class ArcanistDifferentialRevisionHash {

  const TABLE_NAME = 'differential_revisionhash';

  const HASH_GIT_COMMIT         = 'gtcm';
  const HASH_GIT_TREE           = 'gttr';
  const HASH_MERCURIAL_COMMIT   = 'hgcm';

  public static function getTypes() {
    return array(
      ArcanistDifferentialRevisionHash::HASH_GIT_COMMIT,
      ArcanistDifferentialRevisionHash::HASH_GIT_TREE,
      ArcanistDifferentialRevisionHash::HASH_MERCURIAL_COMMIT,
    );
  }

}
