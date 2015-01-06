<?php

abstract class ArcanistFutureLinter extends ArcanistLinter {

  private $futures;

  abstract protected function buildFutures(array $paths);
  abstract protected function resolveFuture($path, Future $future);

  final protected function getFuturesLimit() {
    return 8;
  }

  final public function willLintPaths(array $paths) {
    $limit = $this->getFuturesLimit();
    $this->futures = id(new FutureIterator(array()))->limit($limit);
    foreach ($this->buildFutures($paths) as $path => $future) {
      $this->futures->addFuture($future, $path);
    }
  }

  final public function lintPath($path) {}

  final public function didRunLinters() {
    if ($this->futures) {
      foreach ($this->futures as $path => $future) {
        $this->willLintPath($path);
        $this->resolveFuture($path, $future);
      }
    }
  }

}
