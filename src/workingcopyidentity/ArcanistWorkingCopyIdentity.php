<?php

/**
 * Interfaces with basic information about the working copy.
 *
 *
 * @task config
 *
 * @group workingcopy
 */
final class ArcanistWorkingCopyIdentity {

  protected $localConfig;
  protected $projectConfig;
  protected $runtimeConfig;
  protected $projectRoot;

  public static function newDummyWorkingCopy() {
    return new ArcanistWorkingCopyIdentity('/', array());
  }

  public static function newFromPath($path) {
    $project_id = null;
    $project_root = null;
    $config = array();
    foreach (Filesystem::walkToRoot($path) as $dir) {
      $config_file = $dir.'/.arcconfig';
      if (!Filesystem::pathExists($config_file)) {
        continue;
      }
      $proj_raw = Filesystem::readFile($config_file);
      $config = self::parseRawConfigFile($proj_raw, $config_file);
      $project_root = $dir;
      break;
    }

    if (!$project_root) {
      foreach (Filesystem::walkToRoot($path) as $dir) {
        $try = array(
          $dir.'/.svn',
          $dir.'/.hg',
          $dir.'/.git',
        );
        foreach ($try as $trydir) {
          if (Filesystem::pathExists($trydir)) {
            $project_root = $dir;
            break 2;
          }
        }
      }
    }

    return new ArcanistWorkingCopyIdentity($project_root, $config);
  }

  public static function newFromRootAndConfigFile(
    $root,
    $config_raw,
    $from_where) {

    $config = self::parseRawConfigFile($config_raw, $from_where);
    return new ArcanistWorkingCopyIdentity($root, $config);
  }

  private static function parseRawConfigFile($raw_config, $from_where) {
    $proj = json_decode($raw_config, true);
    if (!is_array($proj)) {
      throw new Exception(
        "Unable to parse '.arcconfig' file '{$from_where}'. The file contents ".
        "should be valid JSON.\n\n".
        "FILE CONTENTS\n".
        substr($raw_config, 0, 2048));
    }
    $required_keys = array(
      'project_id',
    );
    foreach ($required_keys as $key) {
      if (!array_key_exists($key, $proj)) {
        throw new Exception(
          "Required key '{$key}' is missing from '.arcconfig' file ".
          "'{$from_where}'.");
      }
    }
    return $proj;
  }

  protected function __construct($root, array $config) {
    $this->projectRoot    = $root;
    $this->projectConfig  = $config;
    $this->localConfig    = array();
    $this->runtimeConfig = array();

    $vc_dirs = array(
      '.git',
      '.hg',
      '.svn',
    );
    $found_meta_dir = false;
    foreach ($vc_dirs as $dir) {
      $meta_path = Filesystem::resolvePath(
        $dir,
        $this->projectRoot);
      if (Filesystem::pathExists($meta_path)) {
        $found_meta_dir = true;
        $local_path = Filesystem::resolvePath(
          'arc/config',
          $meta_path);
        if (Filesystem::pathExists($local_path)) {
          $file = Filesystem::readFile($local_path);
          if ($file) {
            $this->localConfig = json_decode($file, true);
          }
        }
        break;
      }
    }

    if (!$found_meta_dir) {
      // Try for a single higher-level .svn directory as used by svn 1.7+
      foreach (Filesystem::walkToRoot($this->projectRoot) as $parent_path) {
        $local_path = Filesystem::resolvePath(
          '.svn/arc/config',
          $parent_path);
        if (Filesystem::pathExists($local_path)) {
          $file = Filesystem::readFile($local_path);
          if ($file) {
            $this->localConfig = json_decode($file, true);
          }
        }
      }
    }

  }

  public function getProjectID() {
    return $this->getConfig('project_id');
  }

  public function getProjectRoot() {
    return $this->projectRoot;
  }

  public function getProjectPath($to_file) {
    return $this->projectRoot.'/'.$to_file;
  }

  public function getConduitURI() {
    return $this->getConfig('conduit_uri');
  }


/* -(  Config  )------------------------------------------------------------- */

  public function getProjectConfig() {
    return $this->projectConfig;
  }

  /**
   * Read a configuration directive from project configuration. This reads ONLY
   * permanent project configuration (i.e., ".arcconfig"), not other
   * configuration sources. See @{method:getConfigFromAnySource} to read from
   * user configuration.
   *
   * @param key   Key to read.
   * @param wild  Default value if key is not found.
   * @return wild Value, or default value if not found.
   *
   * @task config
   */
  public function getConfig($key, $default = null) {
    $settings = new ArcanistSettings();

    $pval = idx($this->projectConfig, $key);

    // Test for older names in the per-project config only, since
    // they've only been used there.
    if ($pval === null) {
      $legacy = $settings->getLegacyName($key);
      if ($legacy) {
        $pval = $this->getConfig($legacy);
      }
    }

    if ($pval === null) {
      $pval = $default;
    } else {
      $pval = $settings->willReadValue($key, $pval);
    }

    return $pval;
  }


  /**
   * Read a configuration directive from local configuration.  This
   * reads ONLY the per-working copy configuration,
   * i.e. .(git|hg|svn)/arc/config, and not other configuration
   * sources.  See @{method:getConfigFromAnySource} to read from any
   * config source or @{method:getConfig} to read permanent
   * project-level config.
   *
   * @task config
   */
  public function getLocalConfig($key, $default=null) {
    return idx($this->localConfig, $key, $default);
  }

  /**
   * Read a configuration directive from any available configuration source.
   * In contrast to @{method:getConfig}, this will look for the directive in
   * local and user configuration in addition to project configuration.
   * The precedence is local > project > user
   *
   * @param key   Key to read.
   * @param wild  Default value if key is not found.
   * @return wild Value, or default value if not found.
   *
   * @task config
   */
  public function getConfigFromAnySource($key, $default = null) {
    $settings = new ArcanistSettings();

    // try runtime config first
    $pval = idx($this->runtimeConfig, $key);

    // try local config
    if ($pval === null) {
      $pval = $this->getLocalConfig($key);
    }

    // then per-project config
    if ($pval === null) {
      $pval = $this->getConfig($key);
    }

    // now try global (i.e. user-level) config
    if ($pval === null) {
      $global_config = ArcanistBaseWorkflow::readGlobalArcConfig();
      $pval = idx($global_config, $key);
    }

    // Finally, try system-level config.
    if ($pval === null) {
      $system_config = ArcanistBaseWorkflow::readSystemArcConfig();
      $pval = idx($system_config, $key);
    }

    if ($pval === null) {
      $pval = $default;
    } else {
      $pval = $settings->willReadValue($key, $pval);
    }

    return $pval;

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

}
