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

$liberate_mode = false;
for ($ii = 0; $ii < $argc; $ii++) {
  if ($argv[$ii] == '--find-paths-for-liberate') {
    $liberate_mode = true;
    unset($argv[$ii]);
  }
}
$argv = array_values($argv);
$argc = count($argv);

if ($argc != 2) {
  $self = basename($argv[0]);
  echo "usage: {$self} <phutil_library_root>\n";
  exit(1);
}

$root = Filesystem::resolvePath($argv[1]);

if (!@file_exists($root.'/__phutil_library_init__.php')) {
  throw new Exception("Provided path is not a phutil library.");
}

if ($liberate_mode) {
  ob_start();
}

echo "Finding phutil modules...\n";
$files = id(new FileFinder($root))
  ->withType('f')
  ->withSuffix('php')
  ->excludePath('*/.*')
  ->setGenerateChecksums(true)
  ->find();

// NOTE: Sorting by filename ensures that hash computation is stable; it is
// important we sort by name instead of by hash because sorting by hash could
// create a bad cache hit if the user swaps the contents of two files.
ksort($files);

$modules = array();
foreach ($files as $file => $hash) {
  if (dirname($file) == $root) {
    continue;
  }
  $modules[Filesystem::readablePath(dirname($file), $root)][] = $hash;
}

echo "Found ".count($files)." files in ".count($modules)." modules.\n";

$signatures = array();
foreach ($modules as $module => $hashes) {
  $hashes = implode(' ', $hashes);
  $signature = md5($hashes);
  $signatures[$module] = $signature;
}

try {
  $cache = Filesystem::readFile($root.'/.phutil_module_cache');
} catch (Exception $ex) {
  $cache = null;
}

$signature_cache = array();
if ($cache) {
  $signature_cache = json_decode($cache, true);
  if (!is_array($signature_cache)) {
    $signature_cache = array();
  }
}

$specs = array();

$need_update = array();
foreach ($signatures as $module => $signature) {
  if (isset($signature_cache[$module]) &&
      $signature_cache[$module]['signature'] == $signature) {
    $specs[$module] = $signature_cache[$module];
  } else {
    $need_update[$module] = true;
  }
}

$futures = array();
foreach ($need_update as $module => $ignored) {
  $futures[$module] = new ExecFuture(
    '%s %s',
    dirname(__FILE__).'/phutil_analyzer.php',
    $root.'/'.$module);
}

if ($futures) {
  echo "Found ".count($specs)." modules in cache; ".
       "analyzing ".count($futures)." modified modules";
  foreach (Futures($futures)->limit(8) as $module => $future) {
    echo ".";
    $specs[$module] = array(
      'signature' => $signatures[$module],
      'spec'      => $future->resolveJSON(),
    );
  }
  echo "\n";
} else {
  echo "All modules were found in cache.\n";
}

$class_map = array();
$requires_class_map = array();
$requires_interface_map = array();
$function_map = array();
foreach ($specs as $module => $info) {
  $spec = $info['spec'];
  foreach (array('class', 'interface') as $type) {
    foreach ($spec['declares'][$type] as $class => $where) {
      if (!empty($class_map[$class])) {
        $prior = $class_map[$class];
        echo "\n";
        echo "Error: definition of {$type} '{$class}' in module '{$module}' ".
             "duplicates prior definition in module '{$prior}'.";
        echo "\n";
        exit(1);
      }
      $class_map[$class] = $module;
    }
  }
  if (!empty($spec['chain']['class'])) {
    $requires_class_map += $spec['chain']['class'];
  }
  if (!empty($spec['chain']['interface'])) {
    $requires_interface_map += $spec['chain']['interface'];
  }
  foreach ($spec['declares']['function'] as $function => $where) {
    if (!empty($function_map[$function])) {
      $prior = $function_map[$function];
      echo "\n";
      echo "Error: definition of function '{$function}' in module '{$module}' ".
           "duplicates prior definition in module '{$prior}'.";
      echo "\n";
      exit(1);
    }
    $function_map[$function] = $module;
  }
}
echo "\n";

ksort($class_map);
ksort($requires_class_map);
ksort($requires_interface_map);
ksort($function_map);

$library_map = array(
  'class' => $class_map,
  'function' => $function_map,
  'requires_class' => $requires_class_map,
  'requires_interface' => $requires_interface_map,
);
$library_map = var_export($library_map, $return_string = true);
$library_map = preg_replace('/\s+$/m', '', $library_map);
$library_map = preg_replace('/array \(/', 'array(', $library_map);

$at = '@';
$map_file = <<<EOPHP
<?php

/**
 * This file is automatically generated. Use 'phutil_mapper.php' to rebuild it.
 * {$at}generated
 */

phutil_register_library_map({$library_map});

EOPHP;

echo "Writing library map file...\n";

Filesystem::writeFile($root.'/__phutil_library_map__.php', $map_file);

if ($liberate_mode) {
  ob_get_clean();
  echo json_encode(array_keys($need_update))."\n";
  return;
}

echo "Writing module cache...\n";

Filesystem::writeFile(
  $root.'/.phutil_module_cache',
  json_encode($specs));

echo "Done.\n";
