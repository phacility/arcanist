<?php

/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * Create and update libphutil libraries.
 *
 * This workflow is unusual and involves reexecuting 'arc liberate' as a
 * subprocess with "--remap" and "--verify". This is because there is no way
 * to unload or reload a library, so every process is stuck with the library
 * definition it had when it first loaded. This is normally fine, but
 * problematic in this case because 'arc liberate' modifies library definitions.
 *
 * @group workflow
 */
final class ArcanistLiberateWorkflow extends ArcanistBaseWorkflow {

  public function getCommandSynopses() {
    return phutil_console_format(<<<EOTEXT
      **liberate** [__path__]
EOTEXT
      );
  }

  public function getCommandHelp() {
    return phutil_console_format(<<<EOTEXT
          Supports: libphutil
          Create or update a libphutil library, generating required metadata
          files like \__init__.php.
EOTEXT
      );
  }

  public function getArguments() {
    return array(
      'all' => array(
        'help' =>
          "Drop the module cache before liberating. This will completely ".
          "reanalyze the entire library. Thorough, but slow!",
      ),
      'force-update' => array(
        'help' =>
          "Force the library map to be updated, even in the presence of ".
          "lint errors.",
      ),
      'remap' => array(
        'hide' => true,
        'help' =>
          "Internal. Run the remap step of liberation. You do not need to ".
          "run this unless you are debugging the workflow.",
      ),
      'verify' => array(
        'hide' => true,
        'help' =>
          "Internal. Run the verify step of liberation. You do not need to ".
          "run this unless you are debugging the workflow.",
      ),
      '*' => 'argv',
    );
  }

  public function run() {
    $argv = $this->getArgument('argv');
    if (count($argv) > 1) {
      throw new ArcanistUsageException(
        "Provide only one path to 'arc liberate'. The path should be a ".
        "directory where you want to create or update a libphutil library.");
    } else if (count($argv) == 0) {
      $path = getcwd();
    } else {
      $path = reset($argv);
    }

    $is_remap = $this->getArgument('remap');
    $is_verify = $this->getArgument('verify');

    $path = Filesystem::resolvePath($path);

    if (Filesystem::pathExists($path) && is_dir($path)) {
      $init = id(new FileFinder($path))
        ->withPath('*/__phutil_library_init__.php')
        ->find();
    } else {
      $init = null;
    }

    if ($init) {
      if (count($init) > 1) {
        throw new ArcanistUsageException(
          "Specified directory contains more than one libphutil library. Use ".
          "a more specific path.");
      }
      $path = Filesystem::resolvePath(dirname(reset($init)), $path);
    } else {
      $found = false;
      foreach (Filesystem::walkToRoot($path) as $dir) {
        if (Filesystem::pathExists($dir.'/__phutil_library_init__.php')) {
          $path = $dir;
          break;
        }
      }
      if (!$found) {
        echo "No library currently exists at that path...\n";
        $this->liberateCreateDirectory($path);
        $this->liberateCreateLibrary($path);
      }
    }

    if ($this->getArgument('remap')) {
      return $this->liberateRunRemap($path);
    }

    if ($this->getArgument('verify')) {
      return $this->liberateRunVerify($path);
    }

    $readable = Filesystem::readablePath($path);
    echo "Using library root at '{$readable}'...\n";

    $this->checkForLooseFiles($path);

    if ($this->getArgument('all')) {
      echo "Dropping module cache...\n";
      Filesystem::remove($path.'/.phutil_module_cache');
    }

    echo "Mapping library...\n";

    // Force a rebuild of the library map before running lint. The remap
    // operation will load the map before regenerating it, so if a class has
    // been renamed (say, from OldClass to NewClass) this rebuild will
    // cause the initial remap to see NewClass and correctly remove includes
    // caused by use of OldClass.
    $this->liberateGetChangedPaths($path);

    $arc_bin = $this->getScriptPath('bin/arc');

    do {
      $future = new ExecFuture(
        '%s liberate --remap -- %s',
        $arc_bin,
        $path);
      $wrote = $future->resolveJSON();
      foreach ($wrote as $wrote_path) {
        echo "Updated '{$wrote_path}'...\n";
      }
    } while ($wrote);

    echo "Verifying library...\n";

    $err = phutil_passthru('%s liberate --verify -- %s', $arc_bin, $path);

    $do_update = (!$err || $this->getArgument('force-update'));

    if ($do_update) {
      echo "Finalizing library map...\n";
      execx('%s %s', $this->getPhutilMapperLocation(), $path);
    }

    if ($err) {
      if ($do_update) {
        echo phutil_console_format(
          "<bg:yellow>**  WARNING  **</bg> Library update forced, but lint ".
          "failures remain.\n");
      } else {
        echo phutil_console_format(
          "<bg:red>**  UNRESOLVED LINT ERRORS  **</bg> This library has ".
          "unresolved lint failures. The library map was not updated. Use ".
          "--force-update to force an update.\n");
      }
    } else {
      echo phutil_console_format(
        "<bg:green>**  OKAY  **</bg> Library updated.\n");
    }

    return $err;
  }

  private function liberateLintModules($path, array $changed) {
    $engine = $this->liberateBuildLintEngine($path, $changed);
    if ($engine) {
      return $engine->run();
    } else {
      return array();
    }
  }

  private function liberateWritePatches(array $results) {
    assert_instances_of($results, 'ArcanistLintResult');
    $wrote = array();

    foreach ($results as $result) {
      if ($result->isPatchable()) {
        $patcher = ArcanistLintPatcher::newFromArcanistLintResult($result);
        $patcher->writePatchToDisk();
        $wrote[] = $result->getPath();
      }
    }

    return $wrote;
  }

  private function liberateBuildLintEngine($path, array $changed) {
    $lint_map = array();
    foreach ($changed as $module) {
      $module_path = $path.'/'.$module;
      $files = Filesystem::listDirectory($module_path);
      $lint_map[$module] = $files;
    }

    $working_copy = ArcanistWorkingCopyIdentity::newFromRootAndConfigFile(
      $path,
      json_encode(
        array(
          'project_id' => '__arcliberate__',
        )),
      'arc liberate');

    $engine = new ArcanistLiberateLintEngine();
    $engine->setWorkingCopy($working_copy);

    $lint_paths = array();
    foreach ($lint_map as $module => $files) {
      foreach ($files as $file) {
        $lint_paths[] = $module.'/'.$file;
      }
    }

    if (!$lint_paths) {
      return null;
    }

    $engine->setPaths($lint_paths);
    $engine->setMinimumSeverity(ArcanistLintSeverity::SEVERITY_ERROR);

    return $engine;
  }


  private function liberateCreateDirectory($path) {
    if (Filesystem::pathExists($path)) {
      if (!is_dir($path)) {
        throw new ArcanistUsageException(
          "Provide a directory to create or update a libphutil library in.");
      }
      return;
    }

    echo "The directory '{$path}' does not exist.";
    if (!phutil_console_confirm('Do you want to create it?')) {
      throw new ArcanistUsageException("Cancelled.");
    }

    execx('mkdir -p %s', $path);
  }

  private function liberateCreateLibrary($path) {
    $init_path = $path.'/__phutil_library_init__.php';
    if (Filesystem::pathExists($init_path)) {
      return;
    }

    echo "Creating new libphutil library in '{$path}'.\n";
    echo "Choose a name for the new library.\n";
    do {
      $name = phutil_console_prompt('What do you want to name this library?');
      if (preg_match('/^[a-z]+$/', $name)) {
        break;
      } else {
        echo "Library name should contain only lowercase letters.\n";
      }
    } while (true);

    $template =
      "<?php\n\n".
      "phutil_register_library('{$name}', __FILE__);\n";

    echo "Writing '__phutil_library_init__.php' to '{$init_path}'...\n";
    Filesystem::writeFile($init_path, $template);
  }

  private function liberateGetChangedPaths($path) {
    $mapper = $this->getPhutilMapperLocation();
    $future = new ExecFuture('%s %s --find-paths-for-liberate', $mapper, $path);
    return $future->resolveJSON();
  }

  private function getScriptPath($script) {
    $root = dirname(phutil_get_library_root('arcanist'));
    return $root.'/'.$script;
  }

  private function getPhutilMapperLocation() {
    return $this->getScriptPath('scripts/phutil_mapper.php');
  }

  private function liberateRunRemap($path) {
    phutil_load_library($path);

    $paths = $this->liberateGetChangedPaths($path);
    $results = $this->liberateLintModules($path, $paths);
    $wrote = $this->liberateWritePatches($results);

    echo json_encode($wrote, true);

    return 0;
  }

  private function liberateRunVerify($path) {
    phutil_load_library($path);

    $paths = $this->liberateGetChangedPaths($path);
    $results = $this->liberateLintModules($path, $paths);

    $renderer = new ArcanistLintRenderer();

    $unresolved = false;
    foreach ($results as $result) {
      foreach ($result->getMessages() as $message) {
        echo $renderer->renderLintResult($result);
        $unresolved = true;
        break;
      }
    }

    return (int)$unresolved;
  }

  /**
   * Sanity check to catch people putting class files in the root of a libphutil
   * library.
   */
  private function checkForLooseFiles($path) {
    foreach (Filesystem::listDirectory($path) as $item) {
      if (!preg_match('/\.php$/', $item)) {
        // Not a ".php" file.
        continue;
      }
      if (preg_match('/^__/', $item)) {
        // Has magic '__' prefix.
        continue;
      }

      echo phutil_console_wrap(
        "WARNING: File '{$item}' is not in a module and won't be loaded. ".
        "Put source files in subdirectories, not the top level directory.\n");
    }
  }

}
