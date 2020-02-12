<?php

function phutil_register_library($library, $path) {
  $path = dirname($path);
  PhutilBootloader::getInstance()->registerLibrary($library, $path);
}

function phutil_register_library_map(array $map) {
  PhutilBootloader::getInstance()->registerLibraryMap($map);
}
