<?php

function ic_get_repository_root() {
  return dirname(dirname(dirname(__FILE__)));
}

function ic_resolve_subpath($subpath = '') {
  return Filesystem::resolvePath(ic_get_repository_root().'/'.$subpath);
}

function ic_constant_tmpdir($name) {
  $tmp = sys_get_temp_dir();
  $user = idx(posix_getpwuid(posix_geteuid()), 'name');
  $dir = ic_join_paths(array($tmp, $user, 'ic', $name));
  Filesystem::createDirectory($dir, 0755, true);
  return $dir;
}

function ic_join_paths(array $paths) {
  $trim_paths = array();
  foreach ($paths as $path) {
    $trim_paths[] = rtrim($path, DIRECTORY_SEPARATOR);
  }
  return implode(DIRECTORY_SEPARATOR, $trim_paths);
}

function ic_get_preferred_repo_uri($uris) {
  // Try to extract an `ssh://` URI due to it having the highest success rate
  $pref_uri = '';

  foreach ($uris as $uri) {
    $cur_uri = idxv($uri, array('fields', 'uri', 'effective'));
    $cur_io_type = idxv($uri, array('fields', 'io', 'effective'));
    if (substr($cur_uri, 0, 3) === 'ssh' && $cur_io_type == 'readwrite') {
      $pref_uri = $cur_uri;
      break;
    }
  }

  if ($pref_uri === '') {
    // No `ssh://` URI found, default to using first URI returned by Conduit
    // NOTE: This has not been encountered yet with our Phabricator instance
    //       for all git repositories.
    $pref_uri = idxv($uris, array(0, 'fields', 'uri', 'effective'));
  }

  return $pref_uri;
}
