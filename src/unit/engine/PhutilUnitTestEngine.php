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
    $project_root = $this->getWorkingCopy()->getProjectRoot();

    $look_here = array();

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

      $path = Filesystem::resolvePath($path, $project_root);

      if (!is_dir($path)) {
        $path = dirname($path);
      }

      if ($path == $library_root) {
        continue;
      }

      if (!Filesystem::isDescendant($path, $library_root)) {
        // We have encountered some kind of symlink maze -- for instance, $path
        // is some symlink living outside the library that links into some file
        // inside the library. Just ignore these cases, since the affected file
        // does not actually lie within the library.
        continue;
      }

      $library_path = Filesystem::readablePath($path, $library_root);
      do {
        $look_here[$library_name.':'.$library_path] = array(
          'library' => $library_name,
          'path'    => $library_path,
        );
        $library_path = dirname($library_path);
      } while ($library_path != '.');
    }

    // Look for any class that extends ArcanistPhutilTestCase inside a
    // __tests__ directory in any parent directory of every affected file.
    //
    // The idea is that "infrastructure/__tests__/" tests defines general tests
    // for all of "infrastructure/", and those tests run for any change in
    // "infrastructure/". However, "infrastructure/concrete/rebar/__tests__/"
    // defines more specific tests that run only when rebar/ (or some
    // subdirectory) changes.

    $run_tests = array();
    foreach ($look_here as $path_info) {
      $library = $path_info['library'];
      $path    = $path_info['path'];

      $symbols = id(new PhutilSymbolLoader())
        ->setType('class')
        ->setLibrary($library)
        ->setPathPrefix($path.'/__tests__/')
        ->setAncestorClass('ArcanistPhutilTestCase')
        ->setConcreteOnly(true)
        ->selectAndLoadSymbols();

      foreach ($symbols as $symbol) {
        $run_tests[$symbol['name']] = true;
      }
    }
    $run_tests = array_keys($run_tests);

    if (!$run_tests) {
      throw new ArcanistNoEffectException("No tests to run.");
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
      $test_case = newv($test_class, array());
      $test_case->setEnableCoverage($enable_coverage);
      $test_case->setProjectRoot($project_root);
      $test_case->setPaths($this->getPaths());
      $results[] = $test_case->run();
    }


    if ($results) {
      $results = call_user_func_array('array_merge', $results);
    }

    return $results;
  }

}
