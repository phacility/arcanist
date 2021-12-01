<?php

function phutil_get_library_root($library) {
  $bootloader = PhutilBootloader::getInstance();
  return $bootloader->getLibraryRoot($library);
}

function phutil_get_library_root_for_path($path) {
  foreach (Filesystem::walkToRoot($path) as $dir) {
    if (Filesystem::pathExists($dir.'/__phutil_library_init__.php')) {
      return $dir;
    }
  }
  return null;
}

function phutil_get_library_name_for_root($path) {
  $path = rtrim(Filesystem::resolvePath($path), '/');

  $bootloader = PhutilBootloader::getInstance();
  $libraries = $bootloader->getAllLibraries();
  foreach ($libraries as $library) {
    $root = $bootloader->getLibraryRoot($library);
    if (rtrim(Filesystem::resolvePath($root), '/') == $path) {
      return $library;
    }
  }

  return null;
}

function phutil_get_current_library_name() {
  $caller = head(debug_backtrace(false));

  $root = phutil_get_library_root_for_path($caller['file']);
  return phutil_get_library_name_for_root($root);
}

function phutil_load_library($path) {
  PhutilBootloader::getInstance()->loadLibrary($path);
}
