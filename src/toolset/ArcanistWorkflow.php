<?php

abstract class ArcanistWorkflow extends Phobject {

  private $runtime;
  private $toolset;
  private $arguments;
  private $configurationEngine;
  private $configurationSourceList;
  private $conduitEngine;

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

  protected function getWorkflowArguments() {
    // TOOLSETS: Temporary!
    return array();
  }

  protected function getWorkflowInformation() {
    // TOOLSETS: Temporary!
    return null;
  }

  public function newPhutilWorkflow() {
    $arguments = $this->getWorkflowArguments();
    assert_instances_of($arguments, 'ArcanistWorkflowArgument');

    $specs = mpull($arguments, 'getPhutilSpecification');

    $phutil_workflow = id(new ArcanistPhutilWorkflow())
      ->setName($this->getWorkflowName())
      ->setWorkflow($this)
      ->setArguments($specs);

    $information = $this->getWorkflowInformation();
    if ($information) {

      $examples = $information->getExamples();
      if ($examples) {
        $examples = implode("\n", $examples);
        $phutil_workflow->setExamples($examples);
      }

      $help = $information->getHelp();
      if (strlen($help)) {
        // Unwrap linebreaks in the help text so we don't get weird formatting.
        $help = preg_replace("/(?<=\S)\n(?=\S)/", " ", $help);

        $phutil_workflow->setHelp($help);
      }

    }

    return $phutil_workflow;
  }

  final public function getToolset() {
    return $this->toolset;
  }

  final public function setToolset(ArcanistToolset $toolset) {
    $this->toolset = $toolset;
    return $this;
  }

  final public function setRuntime(ArcanistRuntime $runtime) {
    $this->runtime = $runtime;
    return $this;
  }

  final public function getRuntime() {
    return $this->runtime;
  }

  final public function getConfig($key) {
    return $this->getConfigurationSourceList()->getConfig($key);
  }

  final public function setConfigurationSourceList(
    ArcanistConfigurationSourceList $config) {
    $this->configurationSourceList = $config;
    return $this;
  }

  final public function getConfigurationSourceList() {
    return $this->configurationSourceList;
  }

  final public function setConfigurationEngine(
    ArcanistConfigurationEngine $configuration_engine) {
    $this->configurationEngine = $configuration_engine;
    return $this;
  }

  final public function getConfigurationEngine() {
    return $this->configurationEngine;
  }

  final protected function getToolsetKey() {
    return $this->getToolset()->getToolsetKey();
  }

  final public function executeWorkflow(PhutilArgumentParser $args) {
    $runtime = $this->getRuntime();

    $this->arguments = $args;
    $caught = null;

    $runtime->pushWorkflow($this);

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

    $runtime->popWorkflow();

    if ($caught) {
      throw $caught;
    }

    return $err;
  }

  final public function getArgument($key) {
    return $this->arguments->getArg($key);
  }

  final protected function newWorkflowArgument($key) {
    return id(new ArcanistWorkflowArgument())
      ->setKey($key);
  }

  final protected function newWorkflowInformation() {
    return new ArcanistWorkflowInformation();
  }

  final protected function getConduitEngine() {
    if (!$this->conduitEngine) {
      $conduit_uri = $this->getConfig('phabricator.uri');

      if (!strlen($conduit_uri)) {
        throw new ArcanistNoURIConduitException(
          pht(
            'This workflow is trying to connect to the Phabricator API, but '.
            'no Phabricator URI is configured. Run "arc help connect" for '.
            'guidance.'));
      }

      $conduit_engine = id(new ArcanistConduitEngine())
        ->setConduitURI($conduit_uri);

      $this->conduitEngine = $conduit_engine;
    }

    return $this->conduitEngine;
  }

  final protected function getLogEngine() {
    return $this->getRuntime()->getLogEngine();
  }

  public function canHandleSignal($signo) {
    return false;
  }

  public function handleSignal($signo) {
    throw new PhutilMethodNotImplementedException();
  }

}
