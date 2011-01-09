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

class PhutilUnitTestEngine extends ArcanistBaseUnitTestEngine {

  public function run() {

    $tests = array();
    foreach ($this->getPaths() as $path) {
      $library_root = phutil_get_library_root_for_path($path);
      if (!$library_root) {
        continue;
      }
      $library_name = phutil_get_library_name_for_root($library_root);

      $path = Filesystem::resolvePath($path);
      if ($path == $library_root) {
        continue;
      }

      if (!is_dir($path)) {
        $path = dirname($path);
      }

      $library_path = Filesystem::readablePath($path, $library_root);
      if (basename($library_path) == '__tests__') {
        // Okay, this is a __tests__ module.
      } else {
        if (phutil_module_exists($library_name, $library_path.'/__tests__')) {
          // This is a module which has a __tests__ module in it.
          $path .= '/__tests__';
        } else {
          // Look for a parent named __tests__.
          $rpos = strrpos($library_path, '/__tests__');
          if ($rpos === false) {
            // No tests to run since there is no child or parent module named
            // __tests__.
            continue;
          }
          // Select the parent named __tests__.
          $path = substr($path, 0, $rpos + strlen('/__tests__'));
        }
      }


      $module_name = Filesystem::readablePath($path, $library_root);
      $module_key = $library_name.':'.$module_name;
      $tests[$module_key] = array(
        'library' => $library_name,
        'root'    => $library_root,
        'module'  => $module_name,
      );
    }

    if (!$tests) {
      throw new ArcanistNoEffectException("No tests to run.");
    }

    $run_tests = array();
    $all_test_classes = phutil_find_class_descendants('ArcanistPhutilTestCase');
    $all_test_classes = array_fill_keys($all_test_classes, true);
    foreach ($tests as $test) {
      $local_classes = phutil_find_classes_declared_in_module(
        $test['library'],
        $test['module']);
      $local_classes = array_fill_keys($local_classes, true);
      $run_tests += array_intersect($local_classes, $all_test_classes);
    }
    $run_tests = array_keys($run_tests);

    if (!$run_tests) {
      throw new ArcanistNoEffectException(
        "No tests to run. You may need to rebuild the phutil library map.");
    }

    $results = array();
    foreach ($run_tests as $test_class) {
      phutil_autoload_class($test_class);
      $test_case = newv($test_class, array());
      $results[] = $test_case->run();
    }
    if ($results) {
      $results = call_user_func_array('array_merge', $results);
    }


    return $results;
  }

}
