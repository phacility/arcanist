<?php

abstract class ArcanistFutureLinter extends ArcanistLinter {

  private $futures;

  abstract protected function buildFutures(array $paths);
  abstract protected function resolveFuture($path, Future $future);

  protected function getFuturesLimit() {
    return 8;
  }

  public function willLintPaths(array $paths) {
    $limit = $this->getFuturesLimit();
    $this->futures = Futures(array())->limit($limit);
    foreach ($this->buildFutures($paths) as $path => $future) {
      $this->futures->addFuture($future, $path);
    }
  }

  public function lintPath($path) {
  }

  public function didRunLinters() {
    if ($this->futures) {
      foreach ($this->futures as $path => $future) {
        $this->willLintPath($path);
        $this->resolveFuture($path, $future);
      }
    }
  }

}
