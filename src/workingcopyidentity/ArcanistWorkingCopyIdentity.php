<?php

/**
 * Interfaces with basic information about the working copy.
 *
 * @task config
 */
final class ArcanistWorkingCopyIdentity extends Phobject {

  private $projectConfig;
  private $projectRoot;
  private $localConfig = array();
  private $localMetaDir;
  private $vcsType;
  private $vcsRoot;

  public static function newDummyWorkingCopy() {
    return self::newFromPathWithConfig('/', array());
  }

  public static function newFromPath($path) {
    return self::newFromPathWithConfig($path, null);
  }

  /**
   * Locate all the information we need about a directory which we presume
   * to be a working copy. Particularly, we want to discover:
   *
   *   - Is the directory inside a working copy (hg, git, svn)?
   *   - If so, what is the root of the working copy?
   *   - Is there a `.arcconfig` file?
   *
   * This is complicated, mostly because Subversion has special rules. In
   * particular:
   *
   *   - Until 1.7, Subversion put a `.svn/` directory inside //every//
   *     directory in a working copy. After 1.7, it //only// puts one at the
   *     root.
   *   - We allow `.arcconfig` to appear anywhere in a Subversion working copy,
   *     and use the one closest to the directory.
   *   - Although we may use a `.arcconfig` from a subdirectory, we store
   *     metadata in the root's `.svn/`, because it's the only one guaranteed
   *     to exist.
   *
   * Users also do these kinds of things in the wild:
   *
   *   - Put working copies inside other working copies.
   *   - Put working copies inside `.git/` directories.
   *   - Create `.arcconfig` files at `/.arcconfig`, `/home/.arcconfig`, etc.
   *
   * This method attempts to be robust against all sorts of possible
   * misconfiguration.
   *
   * @param string    Path to load information for, usually the current working
   *                  directory (unless running unit tests).
   * @param map|null  Pass `null` to locate and load a `.arcconfig` file if one
   *                  exists. Pass a map to use it to set configuration.
   * @return ArcanistWorkingCopyIdentity Constructed working copy identity.
   */
  private static function newFromPathWithConfig($path, $config) {
    $project_root = null;
    $vcs_root = null;
    $vcs_type = null;

    // First, find the outermost directory which is a Git, Mercurial or
    // Subversion repository, if one exists. We go from the top because this
    // makes it easier to identify the root of old SVN working copies (which
    // have a ".svn/" directory inside every directory in the working copy) and
    // gives us the right result if you have a Git repository inside a
    // Subversion repository or something equally ridiculous.

    $paths = Filesystem::walkToRoot($path);
    $config_paths = array();
    $paths = array_reverse($paths);
    foreach ($paths as $path_key => $parent_path) {
      $try = array(
        'git' => $parent_path.'/.git',
        'hg'  => $parent_path.'/.hg',
        'svn' => $parent_path.'/.svn',
      );

      foreach ($try as $vcs => $try_dir) {
        if (!Filesystem::pathExists($try_dir)) {
          continue;
        }

        // NOTE: We're distinguishing between the `$project_root` and the
        // `$vcs_root` because they may not be the same in Subversion. Normally,
        // they are identical. However, in Subversion, the `$vcs_root` is the
        // base directory of the working copy (the directory which has the
        // `.svn/` directory, after SVN 1.7), while the `$project_root` might
        // be any subdirectory of the `$vcs_root`: it's the the directory
        // closest to the current directory which contains a `.arcconfig`.

        $project_root = $parent_path;
        $vcs_root = $parent_path;
        $vcs_type = $vcs;
        if ($vcs == 'svn') {
          // For Subversion, we'll look for a ".arcconfig" file here or in
          // any subdirectory, starting at the deepest subdirectory.
          $config_paths = array_slice($paths, $path_key);
          $config_paths = array_reverse($config_paths);
        } else {
          // For Git and Mercurial, we'll only look for ".arcconfig" right here.
          $config_paths = array($parent_path);
        }
        break;
      }
    }

    $console = PhutilConsole::getConsole();

    $looked_in = array();
    foreach ($config_paths as $config_path) {
      $config_file = $config_path.'/.arcconfig';
      $looked_in[] = $config_file;
      if (Filesystem::pathExists($config_file)) {
        // We always need to examine the filesystem to look for `.arcconfig`
        // so we can set the project root correctly. We might or might not
        // actually read the file: if the caller passed in configuration data,
        // we'll ignore the actual file contents.
        $project_root = $config_path;
        if ($config === null) {
          $console->writeLog(
            "%s\n",
            pht(
              'Working Copy: Reading %s from "%s".',
              '.arcconfig',
              $config_file));
          $config_data = Filesystem::readFile($config_file);
          $config = self::parseRawConfigFile($config_data, $config_file);
        }
        break;
      }
    }

    if ($config === null) {
      if ($looked_in) {
        $console->writeLog(
          "%s\n",
          pht(
            'Working Copy: Unable to find %s in any of these locations: %s.',
            '.arcconfig',
            implode(', ', $looked_in)));
      } else {
        $console->writeLog(
          "%s\n",
          pht(
            'Working Copy: No candidate locations for %s from '.
            'this working directory.',
            '.arcconfig'));
      }
      $config = array();
    }

    if ($project_root === null) {
      // We aren't in a working directory at all. This is fine if we're
      // running a command like "arc help". If we're running something that
      // requires a working directory, an exception will be raised a little
      // later on.
      $console->writeLog(
        "%s\n",
        pht('Working Copy: Path "%s" is not in any working copy.', $path));
      return new ArcanistWorkingCopyIdentity($path, $config);
    }

    $console->writeLog(
      "%s\n",
      pht(
        'Working Copy: Path "%s" is part of `%s` working copy "%s".',
        $path,
        $vcs_type,
        $vcs_root));

    $console->writeLog(
      "%s\n",
      pht(
        'Working Copy: Project root is at "%s".',
        $project_root));

    $identity = new ArcanistWorkingCopyIdentity($project_root, $config);
    $identity->localMetaDir = $vcs_root.'/.'.$vcs_type;
    $identity->localConfig = $identity->readLocalArcConfig();
    $identity->vcsType = $vcs_type;
    $identity->vcsRoot = $vcs_root;

    return $identity;
  }

  public static function newFromRootAndConfigFile(
    $root,
    $config_raw,
    $from_where) {

    if ($config_raw === null) {
      $config = array();
    } else {
      $config = self::parseRawConfigFile($config_raw, $from_where);
    }

    return self::newFromPathWithConfig($root, $config);
  }

  private static function parseRawConfigFile($raw_config, $from_where) {
    try {
      return phutil_json_decode($raw_config);
    } catch (PhutilJSONParserException $ex) {
      throw new PhutilProxyException(
        pht("Unable to parse '%s' file '%s'.", '.arcconfig', $from_where),
        $ex);
    }
  }

  private function __construct($root, array $config) {
    $this->projectRoot = $root;
    $this->projectConfig = $config;
  }

  public function getProjectRoot() {
    return $this->projectRoot;
  }

  public function getProjectPath($to_file) {
    return $this->projectRoot.'/'.$to_file;
  }

  public function getVCSType() {
    return $this->vcsType;
  }

  public function getVCSRoot() {
    return $this->vcsRoot;
  }


/* -(  Config  )------------------------------------------------------------- */

  public function readProjectConfig() {
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
  public function getProjectConfig($key, $default = null) {
    $settings = new ArcanistSettings();

    $pval = idx($this->projectConfig, $key);

    // Test for older names in the per-project config only, since
    // they've only been used there.
    if ($pval === null) {
      $legacy = $settings->getLegacyName($key);
      if ($legacy) {
        $pval = $this->getProjectConfig($legacy);
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
   * Read a configuration directive from local configuration. This
   * reads ONLY the per-working copy configuration,
   * i.e. .(git|hg|svn)/arc/config, and not other configuration
   * sources. See @{method:getConfigFromAnySource} to read from any
   * config source or @{method:getProjectConfig} to read permanent
   * project-level config.
   *
   * @task config
   */
  public function getLocalConfig($key, $default = null) {
    return idx($this->localConfig, $key, $default);
  }

  public function readLocalArcConfig() {
    if (strlen($this->localMetaDir)) {
      $local_path = Filesystem::resolvePath('arc/config', $this->localMetaDir);

      $console = PhutilConsole::getConsole();

      if (Filesystem::pathExists($local_path)) {
        $console->writeLog(
          "%s\n",
          pht(
            'Config: Reading local configuration file "%s"...',
            $local_path));

        try {
          $json = Filesystem::readFile($local_path);
          return phutil_json_decode($json);
        } catch (PhutilJSONParserException $ex) {
          throw new PhutilProxyException(
            pht("Failed to parse '%s' as JSON.", $local_path),
            $ex);
        }
      } else {
        $console->writeLog(
          "%s\n",
          pht(
            'Config: Did not find local configuration at "%s".',
            $local_path));
      }
    }

    return array();
  }

  public function writeLocalArcConfig(array $config) {
    $json_encoder = new PhutilJSON();
    $json = $json_encoder->encodeFormatted($config);

    $dir = $this->localMetaDir;
    if (!strlen($dir)) {
      throw new Exception(pht('No working copy to write config into!'));
    }

    $local_dir = $dir.DIRECTORY_SEPARATOR.'arc';
    if (!Filesystem::pathExists($local_dir)) {
      Filesystem::createDirectory($local_dir, 0755);
    }

    $config_file = $local_dir.DIRECTORY_SEPARATOR.'config';
    Filesystem::writeFile($config_file, $json);
  }

}
