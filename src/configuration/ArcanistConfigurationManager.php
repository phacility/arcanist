<?php

/**
 * This class holds everything related to configuration and configuration files.
 */
final class ArcanistConfigurationManager extends Phobject {

  private $runtimeConfig = array();
  private $workingCopy = null;
  private $customArcrcFilename = null;
  private $userConfigCache = null;

  public function setWorkingCopyIdentity(
    ArcanistWorkingCopyIdentity $working_copy) {
    $this->workingCopy = $working_copy;
    return $this;
  }

/* -(  Get config  )--------------------------------------------------------- */

  const CONFIG_SOURCE_RUNTIME = 'runtime';
  const CONFIG_SOURCE_LOCAL   = 'local';
  const CONFIG_SOURCE_PROJECT = 'project';
  const CONFIG_SOURCE_USER    = 'user';
  const CONFIG_SOURCE_SYSTEM  = 'system';
  const CONFIG_SOURCE_DEFAULT = 'default';

  public function getProjectConfig($key) {
    if ($this->workingCopy) {
      return $this->workingCopy->getProjectConfig($key);
    }
    return null;
  }

  public function getLocalConfig($key) {
    if ($this->workingCopy) {
      return $this->workingCopy->getLocalConfig($key);
    }
    return null;
  }

  public function getWorkingCopyIdentity() {
    return $this->workingCopy;
  }

  /**
   * Read a configuration directive from any available configuration source.
   * This includes the directive in local, user and system configuration in
   * addition to project configuration, and configuration provided as command
   * arguments ("runtime").
   * The precedence is runtime > local > project > user > system
   *
   * @param key   Key to read.
   * @param wild  Default value if key is not found.
   * @return wild Value, or default value if not found.
   *
   * @task config
   */
  public function getConfigFromAnySource($key, $default = null) {
    $all = $this->getConfigFromAllSources($key);
    return empty($all) ? $default : head($all);
  }

  /**
   * For the advanced case where you want customized configuration handling.
   *
   * Reads the configuration from all available sources, returning a map (array)
   * of results, with the source as key. Missing values will not be in the map,
   * so an empty array will be returned if no results are found.
   *
   * The map is ordered by the canonical sources precedence, which is:
   * runtime > local > project > user > system
   *
   * @param key   Key to read
   * @return array Mapping of source => value read. Sources with no value are
   *               not in the array.
   *
   * @task config
   */
  public function getConfigFromAllSources($key) {
    $results = array();
    $settings = new ArcanistSettings();

    $pval = idx($this->runtimeConfig, $key);
    if ($pval !== null) {
      $results[self::CONFIG_SOURCE_RUNTIME] =
        $settings->willReadValue($key, $pval);
    }

    $pval = $this->getLocalConfig($key);
    if ($pval !== null) {
      $results[self::CONFIG_SOURCE_LOCAL] =
        $settings->willReadValue($key, $pval);
    }

    $pval = $this->getProjectConfig($key);
    if ($pval !== null) {
      $results[self::CONFIG_SOURCE_PROJECT] =
        $settings->willReadValue($key, $pval);
    }

    $user_config = $this->readUserArcConfig();

    // For "aliases" coming from the user config file specifically, read the
    // top level "aliases" key instead of the "aliases" key inside the "config"
    // setting. Aliases were originally user-specific but later became standard
    // configuration, which is why this works oddly.
    if ($key === 'aliases') {
      $pval = idx($this->readUserConfigurationFile(), $key);
    } else {
      $pval = idx($user_config, $key);
    }

    if ($pval !== null) {
      $results[self::CONFIG_SOURCE_USER] =
        $settings->willReadValue($key, $pval);
    }

    $system_config = $this->readSystemArcConfig();
    $pval = idx($system_config, $key);
    if ($pval !== null) {
      $results[self::CONFIG_SOURCE_SYSTEM] =
        $settings->willReadValue($key, $pval);
    }

    $default_config = $this->readDefaultConfig();
    if (array_key_exists($key, $default_config)) {
      $results[self::CONFIG_SOURCE_DEFAULT] = $default_config[$key];
    }

    return $results;
  }

  /**
   * Sets a runtime config value that takes precedence over any static
   * config values.
   *
   * @param key   Key to set.
   * @param value The value of the key.
   *
   * @task config
   */
  public function setRuntimeConfig($key, $value) {
    $this->runtimeConfig[$key] = $value;

    return $this;
  }

/* -(  Read/write config )--------------------------------------------------- */

  public function readLocalArcConfig() {
    if ($this->workingCopy) {
      return $this->workingCopy->readLocalArcConfig();
    }

    return array();
  }

  public function writeLocalArcConfig(array $config) {
    if ($this->workingCopy) {
      return $this->workingCopy->writeLocalArcConfig($config);
    }

    throw new Exception(pht('No working copy to write config to!'));
  }

  /**
   * This is probably not the method you're looking for; try
   * @{method:readUserArcConfig}.
   */
  public function readUserConfigurationFile() {
    if ($this->userConfigCache === null) {
      $user_config = array();
      $user_config_path = $this->getUserConfigurationFileLocation();

      $console = PhutilConsole::getConsole();
      if (Filesystem::pathExists($user_config_path)) {
        $console->writeLog(
          "%s\n",
          pht(
            'Config: Reading user configuration file "%s"...',
            $user_config_path));

        if (!phutil_is_windows()) {
          $mode = fileperms($user_config_path);
          if (!$mode) {
            throw new Exception(
              pht(
                'Unable to read file permissions for "%s"!',
                $user_config_path));
          }
          if ($mode & 0177) {
            // Mode should allow only owner access.
            $prompt = pht(
              "File permissions on your %s are too open. ".
              "Fix them by chmod'ing to 600?",
              '~/.arcrc');
            if (!phutil_console_confirm($prompt, $default_no = false)) {
              throw new ArcanistUsageException(
                pht('Set %s to file mode 600.', '~/.arcrc'));
            }
            execx('chmod 600 %s', $user_config_path);

            // Drop the stat cache so we don't read the old permissions if
            // we end up here again. If we don't do this, we may prompt the user
            // to fix permissions multiple times.
            clearstatcache();
          }
        }

        $user_config_data = Filesystem::readFile($user_config_path);
        try {
          $user_config = phutil_json_decode($user_config_data);
        } catch (PhutilJSONParserException $ex) {
          throw new PhutilProxyException(
            pht("Your '%s' file is not a valid JSON file.", '~/.arcrc'),
            $ex);
        }
      } else {
        $console->writeLog(
          "%s\n",
          pht(
            'Config: Did not find user configuration at "%s".',
            $user_config_path));
      }

      $this->userConfigCache = $user_config;
    }

    return $this->userConfigCache;
  }

  /**
   * This is probably not the method you're looking for; try
   * @{method:writeUserArcConfig}.
   */
  public function writeUserConfigurationFile($config) {
    $json_encoder = new PhutilJSON();
    $json = $json_encoder->encodeFormatted($config);

    $path = $this->getUserConfigurationFileLocation();
    Filesystem::writeFile($path, $json);

    if (!phutil_is_windows()) {
      execx('chmod 600 %s', $path);
    }
  }

  public function setUserConfigurationFileLocation($custom_arcrc) {
    if (!Filesystem::pathExists($custom_arcrc)) {
      throw new Exception(
        pht('Custom %s file was specified, but it was not found!', 'arcrc'));
    }

    $this->customArcrcFilename = $custom_arcrc;
    $this->userConfigCache = null;
    return $this;
  }

  public function getUserConfigurationFileLocation() {
    if (strlen($this->customArcrcFilename)) {
      return $this->customArcrcFilename;
    }

    if (phutil_is_windows()) {
      return getenv('APPDATA').'/.arcrc';
    } else {
      return getenv('HOME').'/.arcrc';
    }
  }

  public function readUserArcConfig() {
    $config = $this->readUserConfigurationFile();

    if (isset($config['config'])) {
      $config = $config['config'];
    }

    return $config;
  }

  public function writeUserArcConfig(array $options) {
    $config = $this->readUserConfigurationFile();
    $config['config'] = $options;
    $this->writeUserConfigurationFile($config);
  }

  public function getSystemArcConfigLocation() {
    if (phutil_is_windows()) {
      return Filesystem::resolvePath(
        'Phabricator/Arcanist/config',
        getenv('ProgramData'));
    } else {
      return '/etc/arcconfig';
    }
  }

  public function readSystemArcConfig() {
    static $system_config;
    if ($system_config === null) {
      $system_config = array();
      $system_config_path = $this->getSystemArcConfigLocation();

      $console = PhutilConsole::getConsole();

      if (Filesystem::pathExists($system_config_path)) {
        $console->writeLog(
          "%s\n",
          pht(
            'Config: Reading system configuration file "%s"...',
            $system_config_path));
        $file = Filesystem::readFile($system_config_path);
        try {
          $system_config = phutil_json_decode($file);
        } catch (PhutilJSONParserException $ex) {
          throw new PhutilProxyException(
            pht(
              "Your '%s' file is not a valid JSON file.",
              $system_config_path),
            $ex);
        }
      } else {
        $console->writeLog(
          "%s\n",
          pht(
            'Config: Did not find system configuration at "%s".',
            $system_config_path));
      }
    }

    return $system_config;
  }

  public function applyRuntimeArcConfig($args) {
    $arcanist_settings = new ArcanistSettings();
    $options = $args->getArg('config');

    foreach ($options as $opt) {
      $opt_config = preg_split('/=/', $opt, 2);
      if (count($opt_config) !== 2) {
        throw new ArcanistUsageException(
          pht(
            "Argument was '%s', but must be '%s'. For example, %s",
            $opt,
            'name=value',
            'history.immutable=true'));
      }

      list($key, $value) = $opt_config;
      $value = $arcanist_settings->willWriteValue($key, $value);
      $this->setRuntimeConfig($key, $value);
    }

    return $this->runtimeConfig;
  }

  public function readDefaultConfig() {
    $settings = new ArcanistSettings();
    return $settings->getDefaultSettings();
  }

}
