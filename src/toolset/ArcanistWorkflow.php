<?php

abstract class ArcanistWorkflow extends Phobject {

  private $toolset;
  private $arguments;


  /**
   * Return the command used to invoke this workflow from the command like,
   * e.g. "help" for @{class:ArcanistHelpWorkflow}.
   *
   * @return string   The command a user types to invoke this workflow.
   */
  abstract public function getWorkflowName();

  protected function runWorkflow() {
    // TOOLSETS: Temporary to get this working.
    throw new PhutilMethodNotImplementedException();
  }

  protected function runWorkflowCleanup() {
    // TOOLSETS: Do we need this?
    return;
  }

  /**
   * Return true if this workflow belongs to the given toolset. Toolsets let
   * you move a set of "arc" commands under some other command.
   *
   * @param ArcanistToolset Current selected toolset.
   * @return bool True if this command supports the provided toolset.
   */
  public function supportsToolset(ArcanistToolset $toolset) {
    // TOOLSETS: Temporary!
    return true;
  }

  public function newPhutilWorkflow() {
    return id(new ArcanistPhutilWorkflow())
      ->setName($this->getWorkflowName())
      ->setWorkflow($this);
  }

  final public function getToolset() {
    return $this->toolset;
  }

  final public function setToolset(ArcanistToolset $toolset) {
    $this->toolset = $toolset;
    return $this;
  }

  final protected function getToolsetKey() {
    return $this->getToolset()->getToolsetKey();
  }

  final public function executeWorkflow(PhutilArgumentParser $args) {
    $this->arguments = $args;
    $caught = null;

    try {
      $err = $this->runWorkflow($args);
    } catch (Exception $ex) {
      $caught = $ex;
    }

    try {
      $this->runWorkflowCleanup();
    } catch (Exception $ex) {
      phlog($ex);
    }

    if ($caught) {
      throw $caught;
    }

    return $err;
  }

  final public function getArgument($key, $default = null) {
    // TOOLSETS: This is a stub for now.
    return $default;

    return $this->arguments->getArg($key, $default);
  }

}
