<?php

/*
 * Copyright 2011 Facebook, Inc.
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

class ArcanistPhutilModuleLinter extends ArcanistLinter {

  const LINT_UNDECLARED_CLASS       = 1;
  const LINT_UNDECLARED_FUNCTION    = 2;
  const LINT_UNDECLARED_INTERFACE   = 3;
  const LINT_UNDECLARED_SOURCE      = 4;
  const LINT_UNUSED_MODULE          = 5;
  const LINT_UNUSED_SOURCE          = 6;
  const LINT_INIT_REBUILD           = 7;
  const LINT_UNKNOWN_CLASS          = 8;
  const LINT_UNKNOWN_FUNCTION       = 9;

  const LINT_ANALYZER_SIGNATURE           = 100;
  const LINT_ANALYZER_DYNAMIC             = 101;
  const LINT_ANALYZER_NO_INIT             = 102;
  const LINT_ANALYZER_MULTIPLE_CLASSES    = 103;

  public function getLintNameMap() {
    return array(
      self::LINT_UNDECLARED_CLASS       => 'Use of Undeclared Class',
      self::LINT_UNDECLARED_FUNCTION    => 'Use of Undeclared Function',
      self::LINT_UNDECLARED_INTERFACE   => 'Use of Undeclared Interface',
      self::LINT_UNDECLARED_SOURCE      => 'Use of Nonexistent File',
      self::LINT_UNUSED_SOURCE          => 'Unused Source',
      self::LINT_UNUSED_MODULE          => 'Unused Module',
      self::LINT_INIT_REBUILD           => 'Rebuilt __init__.php File',
      self::LINT_UNKNOWN_CLASS          => 'Unknown Class',
      self::LINT_UNKNOWN_FUNCTION       => 'Unknown Function',
      self::LINT_ANALYZER_SIGNATURE     => 'Analyzer: Bad Call Signature',
      self::LINT_ANALYZER_DYNAMIC       => 'Analyzer: Dynamic Dependency',
      self::LINT_ANALYZER_NO_INIT       => 'Analyzer: No __init__.php File',
      self::LINT_ANALYZER_MULTIPLE_CLASSES
        => 'Analyzer: File Declares Multiple Classes',
    );
  }

  public function getLinterName() {
    return 'PHU';
  }

  public function getLintSeverityMap() {
    return array(
      self::LINT_ANALYZER_DYNAMIC => ArcanistLintSeverity::SEVERITY_WARNING,
    );
  }

  private $moduleInfo = array();
  private $unknownClasses = array();
  private $unknownFunctions = array();

  private function setModuleInfo($key, array $info) {
    $this->moduleInfo[$key] = $info;
  }

  private function getModulePathOnDisk($key) {
    $info = $this->moduleInfo[$key];
    return $info['root'].'/'.$info['module'];
  }

  private function getModuleDisplayName($key) {
    $info = $this->moduleInfo[$key];
    return $info['module'];
  }

  private function isPhutilLibraryMetadata($path) {
    $file = basename($path);
    return !strncmp('__phutil_library_', $file, strlen('__phutil_library_'));
  }

  public function willLintPaths(array $paths) {

    if ($paths) {
      if (!xhpast_is_available()) {
        throw new Exception(xhpast_get_build_instructions());
      }
    }

    $modules = array();
    $moduleinfo = array();

    $project_root = $this->getEngine()->getWorkingCopy()->getProjectRoot();

    foreach ($paths as $path) {
      $absolute_path = $project_root.'/'.$path;
      $library_root = phutil_get_library_root_for_path($absolute_path);
      if (!$library_root) {
        continue;
      }
      if ($this->isPhutilLibraryMetadata($path)) {
        continue;
      }
      $library_name = phutil_get_library_name_for_root($library_root);
      if (!is_dir($path)) {
        $path = dirname($path);
      }
      $path = Filesystem::resolvePath(
        $path,
        $project_root);
      if ($path == $library_root) {
        continue;
      }
      $module_name = Filesystem::readablePath($path, $library_root);
      $module_key = $library_name.':'.$module_name;
      if (empty($modules[$module_key])) {
        $modules[$module_key] = $module_key;
        $this->setModuleInfo($module_key, array(
          'library' => $library_name,
          'root'    => $library_root,
          'module'  => $module_name,
        ));
      }
    }

    if (!$modules) {
      return;
    }

    $modules = array_keys($modules);

    $arc_root = phutil_get_library_root('arcanist');
    $bin = dirname($arc_root).'/scripts/phutil_analyzer.php';

    $futures = array();
    foreach ($modules as $mkey => $key) {
      $disk_path = $this->getModulePathOnDisk($key);
      if (Filesystem::pathExists($disk_path)) {
        $futures[$key] = new ExecFuture(
          '%s %s',
          $bin,
          $disk_path);
      } else {
        // This can occur in git when you add a module in HEAD and then remove
        // it in unstaged changes in the working copy. Just ignore it.
        unset($modules[$mkey]);
      }
    }

    $requirements = array();
    foreach (Futures($futures) as $key => $future) {
      $requirements[$key] = $future->resolveJSON();
    }

    $dependencies = array();
    $futures = array();
    foreach ($requirements as $key => $requirement) {
      foreach ($requirement['messages'] as $message) {
        list($where, $text, $code, $description) = $message;
        if ($where) {
          $where = array($where);
        }
        $this->raiseLintInModule(
          $key,
          $code,
          $description,
          $where,
          $text);
      }

      foreach ($requirement['requires']['module'] as $req_module => $where) {
        if (isset($requirements[$req_module])) {
          $dependencies[$req_module] = $requirements[$req_module];
        } else {
          list($library_name, $module_name) = explode(':', $req_module);
          $library_root = phutil_get_library_root($library_name);
          $this->setModuleInfo($req_module, array(
            'library' => $library_name,
            'root'    => $library_root,
            'module'  => $module_name,
          ));
          $disk_path = $this->getModulePathOnDisk($req_module);
          if (Filesystem::pathExists($disk_path)) {
            $futures[$req_module] = new ExecFuture(
              '%s %s',
              $bin,
              $disk_path);
          } else {
            $dependencies[$req_module] = array();
          }
        }
      }
    }

    foreach (Futures($futures) as $key => $future) {
      $dependencies[$key] = $future->resolveJSON();
    }

    foreach ($requirements as $key => $spec) {
      $deps = array_intersect_key(
        $dependencies,
        $spec['requires']['module']);
      $this->lintModule($key, $spec, $deps);
    }
  }

  private function lintModule($key, $spec, $deps) {
    $resolvable = array();
    $need_classes = array();
    $need_functions = array();
    $drop_modules = array();

    $used = array();
    static $types = array(
      'class'     => self::LINT_UNDECLARED_CLASS,
      'interface' => self::LINT_UNDECLARED_INTERFACE,
      'function'  => self::LINT_UNDECLARED_FUNCTION,
    );
    foreach ($types as $type => $lint_code) {
      foreach ($spec['requires'][$type] as $name => $places) {
        $declared = $this->checkDependency(
          $type,
          $name,
          $deps);
        if (!$declared) {
          $module = $this->getModuleDisplayName($key);
          $message = $this->raiseLintInModule(
            $key,
            $lint_code,
            "Module '{$module}' uses {$type} '{$name}' but does not include ".
            "any module which declares it.",
            $places);

          if ($type == 'class' || $type == 'interface') {
            $loader = new PhutilSymbolLoader();
            $loader->setType($type);
            $loader->setName($name);
            $symbols = $loader->selectSymbolsWithoutLoading();
            if ($symbols) {
              $class_spec = reset($symbols);
              try {
                $loader->selectAndLoadSymbols();
                $loaded = true;
              } catch (PhutilMissingSymbolException $ex) {
                $loaded = false;
              } catch (PhutilBootloaderException $ex) {
                $loaded = false;
              }
              if ($loaded) {
                $resolvable[] = $message;
                $need_classes[$name] = $class_spec;
              } else {
                if (empty($this->unknownClasses[$name])) {
                  $this->unknownClasses[$name] = true;
                  $library = $class_spec['library'];
                  $this->raiseLintInModule(
                    $key,
                    self::LINT_UNKNOWN_CLASS,
                    "Class '{$name}' exists in the library map for library ".
                    "'{$library}', but could not be loaded. You may need to ".
                    "rebuild the library map.",
                    $places);
                }
              }
            } else {
              if (empty($this->unknownClasses[$name])) {
                $this->unknownClasses[$name] = true;
                $this->raiseLintInModule(
                  $key,
                  self::LINT_UNKNOWN_CLASS,
                  "Class '{$name}' could not be found in any known library. ".
                  "You may need to rebuild the map for the library which ".
                  "contains it.",
                  $places);
              }
            }
          } else {
            $loader = new PhutilSymbolLoader();
            $loader->setType($type);
            $loader->setName($name);
            $symbols = $loader->selectSymbolsWithoutLoading();
            if ($symbols) {
              $func_spec = reset($symbols);
              try {
                $loader->selectAndLoadSymbols();
                $loaded = true;
              } catch (PhutilMissingSymbolException $ex) {
                $loaded = false;
              } catch (PhutilBootloaderException $ex) {
                $loaded = false;
              }
              if ($loaded) {
                $resolvable[] = $message;
                $need_functions[$name] = $func_spec;
              } else {
                if (empty($this->unknownFunctions[$name])) {
                  $this->unknownFunctions[$name] = true;
                  $library = $func_spec['library'];
                  $this->raiseLintInModule(
                    $key,
                    self::LINT_UNKNOWN_FUNCTION,
                    "Function '{$name}' exists in the library map for library ".
                    "'{$library}', but could not be loaded. You may need to ".
                    "rebuild the library map.",
                    $places);
                }
              }
            } else {
              if (empty($this->unknownFunctions[$name])) {
                $this->unknownFunctions[$name] = true;
                $this->raiseLintInModule(
                  $key,
                  self::LINT_UNKNOWN_FUNCTION,
                  "Function '{$name}' could not be found in any known ".
                  "library. You may need to rebuild the map for the library ".
                  "which contains it.",
                  $places);
              }
            }
          }
        }
        $used[$declared] = true;
      }
    }

    $unused = array_diff_key($deps, $used);
    foreach ($unused as $unused_module_key => $ignored) {
      $module = $this->getModuleDisplayName($key);
      $unused_module = $this->getModuleDisplayName($unused_module_key);
      $resolvable[] = $this->raiseLintInModule(
        $key,
        self::LINT_UNUSED_MODULE,
        "Module '{$module}' requires module '{$unused_module}' but does not ".
        "use anything it declares.",
        $spec['requires']['module'][$unused_module_key]);
      $drop_modules[] = $unused_module_key;
    }

    foreach ($spec['requires']['source'] as $file => $where) {
      if (empty($spec['declares']['source'][$file])) {
        $module = $this->getModuleDisplayName($key);
        $resolvable[] = $this->raiseLintInModule(
          $key,
          self::LINT_UNDECLARED_SOURCE,
          "Module '{$module}' requires source '{$file}', but it does not ".
          "exist.",
          $where);
      }
    }

    foreach ($spec['declares']['source'] as $file => $ignored) {
      if (empty($spec['requires']['source'][$file])) {
        $module = $this->getModuleDisplayName($key);
        $resolvable[] = $this->raiseLintInModule(
          $key,
          self::LINT_UNUSED_SOURCE,
          "Module '{$module}' does not include source file '{$file}'.",
          null);
      }
    }

    if ($resolvable) {
      $new_file = $this->buildNewModuleInit(
        $key,
        $spec,
        $need_classes,
        $need_functions,
        $drop_modules);
      $init_path = $this->getModulePathOnDisk($key).'/__init__.php';
      $try_path = Filesystem::readablePath($init_path);
      if (Filesystem::pathExists($try_path)) {
        $init_path = $try_path;
        $old_file = Filesystem::readFile($init_path);
      } else {
        $old_file = '';
      }
      $this->willLintPath($init_path);
      $message = $this->raiseLintAtOffset(
        null,
        self::LINT_INIT_REBUILD,
        "This generated phutil '__init__.php' file is suggested to address ".
        "lint problems with static dependencies in the module.",
        $old_file,
        $new_file);
      $message->setDependentMessages($resolvable);
      foreach ($resolvable as $message) {
        $message->setObsolete(true);
      }
      $message->setGenerateFile(true);
    }
  }

  private function buildNewModuleInit(
    $key,
    $spec,
    $need_classes,
    $need_functions,
    $drop_modules) {

    $init = array();
    $init[] = '<?php';

    $at = '@';
    $init[] = <<<EOHEADER
/**
 * This file is automatically generated. Lint this module to rebuild it.
 * {$at}generated
 */

EOHEADER;
    $init[] = null;

    $modules = $spec['requires']['module'];

    foreach ($drop_modules as $drop) {
      unset($modules[$drop]);
    }

    foreach ($need_classes as $need => $class_spec) {
      $modules[$class_spec['library'].':'.$class_spec['module']] = true;
    }

    foreach ($need_functions as $need => $func_spec) {
      $modules[$func_spec['library'].':'.$func_spec['module']] = true;
    }

    ksort($modules);

    $last = null;
    foreach ($modules as $module_key => $ignored) {

      if (is_array($ignored)) {
        $in_init = false;
        $in_file = false;
        foreach ($ignored as $where) {
          list($file, $line) = explode(':', $where);
          if ($file == '__init__.php') {
            $in_init = true;
          } else {
            $in_file = true;
          }
        }
        if ($in_file && !$in_init) {
          // If this is a runtime include, don't try to put it in the
          // __init__ file.
          continue;
        }
      }

      list($library, $module_name) = explode(':', $module_key);
      if ($last != $library) {
        $last = $library;
        if ($last != null) {
          $init[] = null;
        }
      }

      $library = "'".addcslashes($library, "'\\")."'";
      $module_name = "'".addcslashes($module_name, "'\\")."'";

      $init[] = "phutil_require_module({$library}, {$module_name});";
    }

    $init[] = null;
    $init[] = null;

    $files = array_keys($spec['declares']['source']);
    sort($files);

    foreach ($files as $file) {
      $file = "'".addcslashes($file, "'\\")."'";
      $init[] = "phutil_require_source({$file});";
    }
    $init[] = null;

    return implode("\n", $init);
  }

  private function checkDependency($type, $name, $deps) {
    foreach ($deps as $key => $dep) {
      if (isset($dep['declares'][$type][$name])) {
        return $key;
      }
    }
    return false;
  }

  public function raiseLintInModule($key, $code, $desc, $places, $text = null) {
    if ($places) {
      foreach ($places as $place) {
        list($file, $offset) = explode(':', $place);
        $this->willLintPath(
          Filesystem::readablePath(
            $this->getModulePathOnDisk($key).'/'.$file,
            $this->getEngine()->getWorkingCopy()->getProjectRoot()));
        return $this->raiseLintAtOffset(
          $offset,
          $code,
          $desc,
          $text);
      }
    } else {
      $this->willLintPath($this->getModuleDisplayName($key));
      return $this->raiseLintAtPath(
        $code,
        $desc);
    }
  }

  public function lintPath($path) {
    return;
  }

}
