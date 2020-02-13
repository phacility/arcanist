<?php

final class ArcanistAlias extends Phobject {

  private $toolset;
  private $trigger;
  private $command;
  private $exception;
  private $configurationSource;

  public static function newFromConfig($key, $value) {
    $alias = new self();

    // Parse older style aliases which were always for the "arc" toolset.
    // When we next write these back into the config file, we'll update them
    // to the modern format.

    // The old format looked like this:
    //
    //   {
    //     "draft": ["diff", "--draft"]
    //   }
    //
    // The new format looks like this:
    //
    //   {
    //     [
    //       "toolset": "arc",
    //       "trigger": "draft",
    //       "command": ["diff", "--draft"]
    //     ]
    //   }
    //
    // For now, we parse the older format and fill in the toolset as "arc".

    $is_list = false;
    $is_dict = false;
    if ($value && is_array($value)) {
      if (phutil_is_natural_list($value)) {
        $is_list = true;
      } else {
        $is_dict = true;
      }
    }

    if ($is_list) {
      $alias->trigger = $key;
      $alias->toolset = 'arc';
      $alias->command = $value;
    } else if ($is_dict) {
      try {
        PhutilTypeSpec::checkMap(
          $value,
          array(
            'trigger' => 'string',
            'toolset' => 'string',
            'command' => 'list<string>',
          ));

        $alias->trigger = idx($value, 'trigger');
        $alias->toolset = idx($value, 'toolset');
        $alias->command = idx($value, 'command');
      } catch (PhutilTypeCheckException $ex) {
        $alias->exception = new PhutilProxyException(
          pht(
            'Found invalid alias definition (with key "%s").',
            $key),
          $ex);
      }
    } else {
      $alias->exception = new Exception(
        pht(
          'Expected alias definition (with key "%s") to be a dictionary.',
          $key));
    }

    return $alias;
  }

  public function setToolset($toolset) {
    $this->toolset = $toolset;
    return $this;
  }

  public function getToolset() {
    return $this->toolset;
  }

  public function setTrigger($trigger) {
    $this->trigger = $trigger;
    return $this;
  }

  public function getTrigger() {
    return $this->trigger;
  }

  public function setCommand(array $command) {
    $this->command = $command;
    return $this;
  }

  public function getCommand() {
    return $this->command;
  }

  public function setException(Exception $exception) {
    $this->exception = $exception;
    return $this;
  }

  public function getException() {
    return $this->exception;
  }

  public function isShellCommandAlias() {
    $command = $this->getCommand();
    if (!$command) {
      return false;
    }

    $head = head($command);
    return preg_match('/^!/', $head);
  }

  public function getStorageDictionary() {
    return array(
      'trigger' => $this->getTrigger(),
      'toolset' => $this->getToolset(),
      'command' => $this->getCommand(),
    );
  }

  public function setConfigurationSource(
    ArcanistConfigurationSource $configuration_source) {
    $this->configurationSource = $configuration_source;
    return $this;
  }

  public function getConfigurationSource() {
    return $this->configurationSource;
  }

}
