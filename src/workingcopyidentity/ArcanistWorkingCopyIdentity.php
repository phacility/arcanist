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

class ArcanistWorkingCopyIdentity {

  protected $projectConfig;
  protected $projectRoot;

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
      $proj = json_decode($proj_raw, true);
      if (!is_array($proj)) {
        throw new Exception(
          "Unable to parse '.arcconfig' file in '{$dir}'. The file contents ".
          "should be valid JSON.");
      }
      $required_keys = array(
        'project_id',
      );
      foreach ($required_keys as $key) {
        if (!array_key_exists($key, $proj)) {
          throw new Exception(
            "Required key '{$key}' is missing from '.arcconfig' file in ".
            "'{$dir}'.");
        }
      }
      $config = $proj;
      $project_root = $dir;
      break;
    }
    return new ArcanistWorkingCopyIdentity($project_root, $config);
  }

  protected function __construct($root, array $config) {
    $this->projectRoot    = $root;
    $this->projectConfig  = $config;
  }

  public function getProjectID() {
    return $this->getConfig('project_id');
  }

  public function getProjectRoot() {
    return $this->projectRoot;
  }

  public function getConduitURI() {
    return $this->getConfig('conduit_uri');
  }

  public function getConfig($key) {
    if (!empty($this->projectConfig[$key])) {
      return $this->projectConfig[$key];
    }
    return null;
  }

}
