<?php

final class ArcanistConfigurationEngine
  extends Phobject {

  private $workingCopy;
  private $arguments;
  private $toolset;

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

  public function newDefaults() {
    $map = $this->newConfigOptionsMap();
    return mpull($map, 'getDefaultValue');
  }

  public function newConfigOptionsMap() {
    $extensions = $this->newEngineExtensions();

    $map = array();
    $alias_map = array();
    foreach ($extensions as $extension) {
      $options = $extension->newConfigurationOptions();

      foreach ($options as $option) {
        $key = $option->getKey();

        $this->validateConfigOptionKey($key, $extension);

        if (isset($map[$key])) {
          throw new Exception(
            pht(
              'Configuration option ("%s") defined by extension "%s" '.
              'conflicts with an existing option. Each option must have '.
              'a unique key.',
              $key,
              get_class($extension)));
        }

        if (isset($alias_map[$key])) {
          throw new Exception(
            pht(
              'Configuration option ("%s") defined by extension "%s" '.
              'conflicts with an alias for another option ("%s"). The '.
              'key and aliases of each option must be unique.',
              $key,
              get_class($extension),
              $alias_map[$key]->getKey()));
        }

        $map[$key] = $option;

        foreach ($option->getAliases() as $alias) {
          $this->validateConfigOptionKey($alias, $extension, $key);

          if (isset($map[$alias])) {
            throw new Exception(
              pht(
                'Configuration option ("%s") defined by extension "%s" '.
                'has an alias ("%s") which conflicts with an existing '.
                'option. The key and aliases of each option must be '.
                'unique.',
                $key,
                get_class($extension),
                $alias));
          }

          if (isset($alias_map[$alias])) {
            throw new Exception(
              pht(
                'Configuration option ("%s") defined by extension "%s" '.
                'has an alias ("%s") which conflicts with the alias of '.
                'another configuration option ("%s"). The key and aliases '.
                'of each option must be unique.',
                $key,
                get_class($extension),
                $alias,
                $alias_map[$alias]->getKey()));
          }

          $alias_map[$alias] = $option;
        }
      }
    }

    return $map;
  }

  private function validateConfigOptionKey(
    $key,
    ArcanistConfigurationEngineExtension $extension,
    $is_alias_of = null) {

    $reserved = array(
      // The presence of this key is used to detect old "~/.arcrc" files, so
      // configuration options may not use it.
      'config',
    );
    $reserved = array_fuse($reserved);

    if (isset($reserved[$key])) {
      throw new Exception(
        pht(
          'Extension ("%s") defines invalid configuration with key "%s". '.
          'This key is reserved.',
          get_class($extension),
          $key));
    }

    $is_ok = preg_match('(^[a-z][a-z0-9._-]{2,}\z)', $key);
    if (!$is_ok) {
      if ($is_alias_of === null) {
        throw new Exception(
          pht(
            'Extension ("%s") defines invalid configuration with key "%s". '.
            'Configuration keys: may only contain lowercase letters, '.
            'numbers, hyphens, underscores, and periods; must start with a '.
            'letter; and must be at least three characters long.',
            get_class($extension),
            $key));
      } else {
        throw new Exception(
          pht(
            'Extension ("%s") defines invalid alias ("%s") for configuration '.
            'key ("%s"). Configuration keys and aliases: may only contain '.
            'lowercase letters, numbers, hyphens, underscores, and periods; '.
            'must start with a letter; and must be at least three characters '.
            'long.',
            get_class($extension),
            $key,
            $is_alias_of));
      }
    }
  }

  private function newEngineExtensions() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass('ArcanistConfigurationEngineExtension')
      ->setUniqueMethod('getExtensionKey')
      ->setContinueOnFailure(true)
      ->execute();
  }

}
