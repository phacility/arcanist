<?php

abstract class ArcanistFutureLinter extends ArcanistLinter {

  private $futures;

  abstract protected function buildFutures(array $paths);
  abstract protected function resolveFuture($path, Future $future);

  final protected function getFuturesLimit() {
    return 8;
  }

  public function willLintPaths(array $paths) {
    $limit = $this->getFuturesLimit();
    $this->futures = id(new FutureIterator(array()))->limit($limit);
    foreach ($this->buildFutures($paths) as $path => $future) {
      $future->setFutureKey($path);
      $this->futures->addFuture($future);
    }
  }

  final public function lintPath($path) {
    return;
  }

  public function didLintPaths(array $paths) {
    if (!$this->futures) {
      return;
    }

    $map = array();
    foreach ($this->futures as $path => $future) {
      $this->setActivePath($path);
      $this->resolveFuture($path, $future);
      $map[$path] = $future;
    }
    $this->futures = array();

    $this->didResolveLinterFutures($map);
  }


  /**
   * Hook for cleaning up resources.
   *
   * This is invoked after a block of futures resolve, and allows linters to
   * discard or clean up any shared resources they no longer need.
   *
   * @param map<string, Future> Map of paths to resolved futures.
   * @return void
   */
  protected function didResolveLinterFutures(array $futures) {
    return;
  }

}
