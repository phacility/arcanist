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
 * Very basic unit test engine which runs libphutil tests.
 *
 * @group unitrun
 */
final class PhutilUnitTestEngine extends ArcanistBaseUnitTestEngine {

  public function run() {

    $bootloader = PhutilBootloader::getInstance();

    $affected_modules = array();
    foreach ($this->getPaths() as $path) {
      $library_root = phutil_get_library_root_for_path($path);
      if (!$library_root) {
        continue;
      }
      $library_name = phutil_get_library_name_for_root($library_root);

      if (!$library_name) {
        throw new Exception(
          "Attempting to run unit tests on a libphutil library which has not ".
          "been loaded, at:\n\n".
          "    {$library_root}\n\n".
          "This probably means one of two things:\n\n".
          "    - You may need to add this library to .arcconfig.\n".
          "    - You may be running tests on a copy of libphutil or arcanist\n".
          "      using a different copy of libphutil or arcanist. This\n".
          "      operation is not supported.");
      }

      $path = Filesystem::resolvePath($path);

      if (!is_dir($path)) {
        $path = dirname($path);
      }

      if ($path == $library_root) {
        continue;
      }

      $library_path = Filesystem::readablePath($path, $library_root);
      do {
        // Add the module and all parent modules as affected modules, which
        // means we'll look for __tests__ to run here and in any containing
        // module.
        $affected_modules[$library_name.':'.$library_path] = array(
          'name' => $library_name,
          'root' => $library_root,
          'path' => $library_path,
        );
        $library_path = dirname($library_path);
      } while ($library_path != '.');
    }

    $tests = array();
    foreach ($affected_modules as $library_info) {
      $library_name = $library_info['name'];
      $library_root = $library_info['root'];
      $module = $library_info['path'];

      if (basename($module) == '__tests__') {
        // Okay, this is a __tests__ module.
      } else {
        $exists = $bootloader->moduleExists(
          $library_name,
          $module.'/__tests__');
        if ($exists) {
          // This is a module which has a __tests__ module in it.
          $module .= '/__tests__';
        } else {
          // Look for a parent named __tests__.
          $rpos = strrpos($module, '/__tests__');
          if ($rpos === false) {
            // No tests to run since there is no child or parent module named
            // __tests__.
            continue;
          }
          // Select the parent named __tests__.
          $module = substr($module, 0, $rpos + strlen('/__tests__'));
        }
      }

      $module_key = $library_name.':'.$module;
      $tests[$module_key] = array(
        'library' => $library_name,
        'root'    => $library_root,
        'module'  => $module,
      );
    }

    if (!$tests) {
      throw new ArcanistNoEffectException("No tests to run.");
    }

    $run_tests = array();
    foreach ($tests as $test) {
      $symbols = id(new PhutilSymbolLoader())
        ->setType('class')
        ->setLibrary($test['library'])
        ->setModule($test['module'])
        ->setAncestorClass('ArcanistPhutilTestCase')
        ->setConcreteOnly(true)
        ->selectAndLoadSymbols();
      foreach ($symbols as $symbol) {
        $run_tests[$symbol['name']] = true;
      }
    }
    $run_tests = array_keys($run_tests);

    if (!$run_tests) {
      throw new ArcanistNoEffectException(
        "No tests to run. You may need to rebuild the phutil library map.");
    }

    $enable_coverage = $this->getEnableCoverage();
    if ($enable_coverage !== false) {
      if (!function_exists('xdebug_start_code_coverage')) {
        if ($enable_coverage === true) {
          throw new ArcanistUsageException(
            "You specified --coverage but xdebug is not available, so ".
            "coverage can not be enabled for PhutilUnitTestEngine.");
        }
      } else {
        $enable_coverage = true;
      }
    }

    $results = array();
    foreach ($run_tests as $test_class) {
      PhutilSymbolLoader::loadClass($test_class);
      $test_case = newv($test_class, array());
      $test_case->setEnableCoverage($enable_coverage);
      $test_case->setProjectRoot($this->getWorkingCopy()->getProjectRoot());
      $test_case->setPaths($this->getPaths());
      $results[] = $test_case->run();
    }


    if ($results) {
      $results = call_user_func_array('array_merge', $results);
    }

    return $results;
  }

}
