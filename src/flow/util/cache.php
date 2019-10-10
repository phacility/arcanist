<?php

function ic_standard_cache(
  PhutilKeyValueCache $cache,
  $namespace = null,
  $memory_limit = 1024,
  $enable_profiler = true) {

  return (new ICCacheFactory())
    ->setNamespace($namespace)
    ->setEnableProfiler($enable_profiler)
    ->addCaches(array(
        (new PhutilInRequestKeyValueCache())
          ->setLimit($memory_limit),
        $cache,
      ))
    ->createStack();
}

function ic_blob_cache_dir() {
  return ic_join_paths(array(
      ic_constant_tmpdir('cache'),
      'blob',
    ));
}

function ic_blob_cache($name) {
  $dir = ic_join_paths(array(
      ic_blob_cache_dir(),
      $name,
    ));
  return (new PhutilDirectoryKeyValueCache())
    ->setCacheDirectory($dir);
}

function ic_list_blob_caches() {
  $dir = ic_blob_cache_dir();
  $cache_dirs = (new FileFinder($dir))
    ->withType('d')
    ->find();
  $data = array();
  foreach ($cache_dirs as $cache_dir) {
    if ($cache_dir === '.') {
      continue;
    }
    $path = ic_join_paths(array($dir, $cache_dir));
    $path_info = new SplFileInfo($path);
    $entries = (new FileFinder($path))
      ->withType('f')
      ->withSuffix('cache')
      ->find();
    $size = 0;
    foreach ($entries as $entry) {
      $entry_path = ic_join_paths(array($path, $entry));
      $entry_info = new SplFileInfo($entry_path);
      $size += $entry_info->getSize();
    }
    $name = $path_info->getBasename();
    $data[] = array(
      'type' => 'blob',
      'name' => $name,
      'mtime' => $path_info->getMTime(),
      'size' => $size,
    );
  }
  return $data;
}

function ic_data_cache_dir() {
  $dir = ic_join_paths(array(
      ic_constant_tmpdir('cache'),
      'data',
    ));
  Filesystem::createDirectory($dir, 0755, true);
  return $dir;
}

function ic_data_cache($name) {
  $cache_file = ic_join_paths(array(
      ic_data_cache_dir(),
      "{$name}.cache",
    ));
  $data = (new PhutilOnDiskKeyValueCache())
    ->setCacheFile($cache_file);
  return new ICDataCacheWrapper($data);
}

function ic_list_data_caches() {
  $dir = ic_data_cache_dir();
  $files = (new FileFinder($dir))
    ->withType('f')
    ->withSuffix('cache')
    ->find();
  $data = array();
  foreach ($files as $file) {
    $path = ic_join_paths(array($dir, $file));
    $info = new SplFileInfo($path);
    $name = $info->getBasename('.cache');
    $data[] = array(
      'type' => 'data',
      'name' => $name,
      'mtime' => $info->getMTime(),
      'size' => $info->getSize(),
    );
  }
  return $data;
}

function ic_list_caches() {
  return array_mergev(array(
      ic_list_data_caches(),
      ic_list_blob_caches(),
    ));
}

function ic_cache($type, $name) {
  switch ($type) {
    case 'blob':
      return ic_blob_cache($name);
    case 'data':
      return ic_data_cache($name);
    default:
      throw new Exception(pht('No cache type "%s" exists.', $type));
  }
}
