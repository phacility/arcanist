<?php

abstract class ArcanistFutureLinter extends ArcanistLinter {

  private $futures;

  abstract protected function buildFutures(array $paths);
  abstract protected function resolveFuture($path, Future $future);

  public function willLintPaths(array $paths) {
    $this->futures = $this->buildFutures($paths);
    if (is_array($this->futures)) {
      foreach ($this->futures as $future) {
        $future->isReady();
      }
      $this->futures = Futures($this->futures);
    }
  }

  public function lintPath($path) {
  }

  public function didRunLinters() {
    if ($this->futures) {
      foreach ($this->futures as $path => $future) {
        $this->resolveFuture($path, $future);
      }
    }
  }

}
