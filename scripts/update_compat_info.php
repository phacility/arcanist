#!/usr/bin/env php
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

require_once dirname(__FILE__).'/__init_script__.php';

$target = 'resources/php_compat_info.json';
echo "Purpose: Updates {$target} used by ArcanistXHPASTLinter.\n";

$ok = include 'PHP/CompatInfo/Autoload.php';
if (!$ok) {
  echo "You need PHP_CompatInfo available in 'include_path'.\n";
  echo "http://php5.laurent-laville.org/compatinfo/\n";
  exit(1);
}

$required = '5.2.3';
$reference = id(new PHP_CompatInfo_Reference_ALL())->getAll();

$output = array();
$output['@'.'generated'] = true;
$output['params'] = array();

foreach (array('functions', 'classes', 'interfaces') as $type) {
  $output[$type] = array();
  foreach ($reference[$type] as $name => $versions) {
    $name = strtolower($name);
    $versions = reset($versions);
    list($min, $max) = $versions;
    if (version_compare($min, $required) > 0) {
      $output[$type][$name] = $min;
    }
    if ($type == 'functions' && isset($versions[2])) {
      $params = explode(', ', $versions[2]);
      foreach ($params as $i => $version) {
        if (version_compare($version, $required) > 0) {
          $output['params'][$name][$i] = $version;
        }
      }
    }
  }
}

file_put_contents(
  dirname(__FILE__).'/../'.$target,
  json_encode($output));

echo "Done.\n";
