<?php

/**
 * @task sharing Sharing Parse Trees
 */
abstract class ArcanistBaseXHPASTLinter extends ArcanistFutureLinter {

  private $futures = array();
  private $trees = array();
  private $exceptions = array();

  final public function getCacheVersion() {
    $parts = array();

    $parts[] = $this->getVersion();

    $path = xhpast_get_binary_path();
    if (Filesystem::pathExists($path)) {
      $parts[] = md5_file($path);
    }

    return implode('-', $parts);
  }

  final protected function raiseLintAtToken(
    XHPASTToken $token,
    $code,
    $desc,
    $replace = null) {
    return $this->raiseLintAtOffset(
      $token->getOffset(),
      $code,
      $desc,
      $token->getValue(),
      $replace);
  }

  final protected function raiseLintAtNode(
    XHPASTNode $node,
    $code,
    $desc,
    $replace = null) {
    return $this->raiseLintAtOffset(
      $node->getOffset(),
      $code,
      $desc,
      $node->getConcreteString(),
      $replace);
  }

  final protected function buildFutures(array $paths) {
    return $this->getXHPASTLinter()->buildSharedFutures($paths);
  }


/* -(  Sharing Parse Trees  )------------------------------------------------ */

  /**
   * Get the linter object which is responsible for building parse trees.
   *
   * When the engine specifies that several XHPAST linters should execute,
   * we designate one of them as the one which will actually build parse trees.
   * The other linters share trees, so they don't have to recompute them.
   *
   * Roughly, the first linter to execute elects itself as the builder.
   * Subsequent linters request builds and retrieve results from it.
   *
   * @return ArcanistBaseXHPASTLinter Responsible linter.
   * @task sharing
   */
  final protected function getXHPASTLinter() {
    $resource_key = 'xhpast.linter';

    // If we're the first linter to run, share ourselves. Otherwise, grab the
    // previously shared linter.

    $engine = $this->getEngine();
    $linter = $engine->getLinterResource($resource_key);
    if (!$linter) {
      $linter = $this;
      $engine->setLinterResource($resource_key, $linter);
    }

    $base_class = __CLASS__;
    if (!($linter instanceof $base_class)) {
      throw new Exception(
        pht(
          'Expected resource "%s" to be an instance of "%s"!',
          $resource_key,
          $base_class));
    }

    return $linter;
  }

  /**
   * Build futures on this linter, for use and to share with other linters.
   *
   * @param list<string> Paths to build futures for.
   * @return list<ExecFuture> Futures.
   * @task sharing
   */
  final protected function buildSharedFutures(array $paths) {
    foreach ($paths as $path) {
      if (!isset($this->futures[$path])) {
        $this->futures[$path] = xhpast_get_parser_future($this->getData($path));
      }
    }
    return array_select_keys($this->futures, $paths);
  }

  /**
   * Get a path's tree from the responsible linter.
   *
   * @param   string           Path to retrieve tree for.
   * @return  XHPASTTree|null  Tree, or null if unparseable.
   * @task sharing
   */
  final protected function getXHPASTTreeForPath($path) {

    // If we aren't the linter responsible for actually building the parse
    // trees, go get the tree from that linter.
    if ($this->getXHPASTLinter() !== $this) {
      return $this->getXHPASTLinter()->getXHPASTTreeForPath($path);
    }

    if (!array_key_exists($path, $this->trees)) {
      $this->trees[$path] = null;
      try {
        $this->trees[$path] = XHPASTTree::newFromDataAndResolvedExecFuture(
          $this->getData($path),
          $this->futures[$path]->resolve());
        $root = $this->trees[$path]->getRootNode();
        $root->buildSelectCache();
        $root->buildTokenCache();
      } catch (Exception $ex) {
        $this->exceptions[$path] = $ex;
      }
    }

    return $this->trees[$path];
  }

  /**
   * Get a path's parse exception from the responsible linter.
   *
   * @param   string          Path to retrieve exception for.
   * @return  Exeption|null   Parse exception, if available.
   * @task sharing
   */
  final protected function getXHPASTExceptionForPath($path) {
    if ($this->getXHPASTLinter() !== $this) {
      return $this->getXHPASTLinter()->getXHPASTExceptionForPath($path);
    }

    return idx($this->exceptions, $path);
  }

}
