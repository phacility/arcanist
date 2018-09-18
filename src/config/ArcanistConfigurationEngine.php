<?php

final class ArcanistConfigurationEngine
  extends Phobject {

  private $workingCopy;
  private $arguments;

  public function setWorkingCopy(ArcanistWorkingCopy $working_copy) {
    $this->workingCopy = $working_copy;
    return $this;
  }

  public function getWorkingCopy() {
    return $this->workingCopy;
  }

  public function setArguments(PhutilArgumentParser $arguments) {
    $this->arguments = $arguments;
    return $this;
  }

  public function getArguments() {
    if (!$this->arguments) {
      throw new PhutilInvalidStateException('setArguments');
    }
    return $this->arguments;
  }

  public function newConfigurationSourceList() {
    $list = new ArcanistConfigurationSourceList();

    $list->addSource(new ArcanistDefaultsConfigurationSource());

    $arguments = $this->getArguments();

    // If the invoker has provided one or more configuration files with
    // "--config-file" arguments, read those files instead of the system
    // and user configuration files. Otherwise, read the system and user
    // configuration files.

    $config_files = $arguments->getArg('config-file');
    if ($config_files) {
      foreach ($config_files as $config_file) {
        $list->addSource(new ArcanistFileConfigurationSource($config_file));
      }
    } else {
      $system_path = $this->getSystemConfigurationFilePath();
      $list->addSource(new ArcanistSystemConfigurationSource($system_path));

      $user_path = $this->getUserConfigurationFilePath();
      $list->addSource(new ArcanistUserConfigurationSource($user_path));
    }


    // If we're running in a working copy, load the ".arcconfig" and any
    // local configuration.
    $working_copy = $this->getWorkingCopy();
    if ($working_copy) {
      $project_path = $working_copy->getProjectConfigurationFilePath();
      if ($project_path !== null) {
        $list->addSource(new ArcanistProjectConfigurationSource($project_path));
      }

      $local_path = $working_copy->getLocalConfigurationFilePath();
      if ($local_path !== null) {
        $list->addSource(new ArcanistLocalConfigurationSource($local_path));
      }
    }

    // If the invoker has provided "--config" arguments, parse those now.
    $runtime_args = $arguments->getArg('config');
    if ($runtime_args) {
      $list->addSource(new ArcanistRuntimeConfigurationSource($runtime_args));
    }

    return $list;
  }

  private function getSystemConfigurationFilePath() {
    if (phutil_is_windows()) {
      return Filesystem::resolvePath(
        'Phabricator/Arcanist/config',
        getenv('ProgramData'));
    } else {
      return '/etc/arcconfig';
    }
  }

  private function getUserConfigurationFilePath() {
    if (phutil_is_windows()) {
      return getenv('APPDATA').'/.arcrc';
    } else {
      return getenv('HOME').'/.arcrc';
    }
  }

}
