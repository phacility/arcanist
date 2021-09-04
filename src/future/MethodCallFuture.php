<?php

/**
 * Degenerate future which resolves by calling a method.
 *
 *   $future = new MethodCallFuture($calculator, 'add', 1, 2);
 *
 * This future is similar to @{class:ImmediateFuture}, but may make it easier
 * to implement exception behavior correctly. See T13666.
 */
final class MethodCallFuture extends Future {

  private $callObject;
  private $callMethod;
  private $callArgv;

  public function __construct($object, $method /* , ...*/ ) {
    $argv = func_get_args();

    $this->callObject = $object;
    $this->callMethod = $method;
    $this->callArgv = array_slice($argv, 2);
  }

  public function isReady() {

    $call = array($this->callObject, $this->callMethod);
    $argv = $this->callArgv;

    $result = call_user_func_array($call, $argv);
    $this->setResult($result);

    return true;
  }

}
