#!/usr/bin/env php
<?php

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

// Grepped from PHP Manual.
$output['functions_windows'] = array(
  'apache_child_terminate' => '',
  'chroot' => '',
  'getrusage' => '',
  'imagecreatefromxpm' => '',
  'lchgrp' => '',
  'lchown' => '',
  'nl_langinfo' => '',
  'strptime' => '',
  'sys_getloadavg' => '',
  'checkdnsrr' => '5.3.0',
  'dns_get_record' => '5.3.0',
  'fnmatch' => '5.3.0',
  'getmxrr' => '5.3.0',
  'getopt' => '5.3.0',
  'imagecolorclosesthwb' => '5.3.0',
  'inet_ntop' => '5.3.0',
  'inet_pton' => '5.3.0',
  'link' => '5.3.0',
  'linkinfo' => '5.3.0',
  'readlink' => '5.3.0',
  'socket_create_pair' => '5.3.0',
  'stream_socket_pair' => '5.3.0',
  'symlink' => '5.3.0',
  'time_nanosleep' => '5.3.0',
  'time_sleep_until' => '5.3.0',
);

file_put_contents(
  phutil_get_library_root('arcanist').'/../'.$target,
  json_encode($output));

echo "Done.\n";
