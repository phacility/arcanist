<?php

/**
 * Interfaces with Subversion while running as a commit hook.
 */
final class ArcanistSubversionHookAPI extends ArcanistHookAPI {

  protected $root;
  protected $transaction;
  protected $repository;

  public function __construct($root, $transaction, $repository) {
    $this->root        = $root;
    $this->transaction = $transaction;
    $this->repository  = $repository;
  }

  public function getCurrentFileData($path) {
    list($err, $file) = exec_manual(
      'svnlook cat --transaction %s %s %s',
      $this->transaction,
      $this->repository,
      $path);

    return ($err? null : $file);
  }

  public function getUpstreamFileData($path) {
    list($err, $file) = exec_manual(
      'svnlook cat %s %s',
      $this->repository,
      $this->root."/$path");
    return ($err ? null : $file);
  }
}
