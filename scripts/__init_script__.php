<?php

/**
 * Adjust 'include_path' to add locations where we'll search for libphutil.
 * We look in these places:
 *
 *  - Next to 'arcanist/'.
 *  - Anywhere in the normal PHP 'include_path'.
 *  - Inside 'arcanist/externals/includes/'.
 *
 * When looking in these places, we expect to find a 'libphutil/' directory.
 */
function arcanist_adjust_php_include_path() {
  // The 'arcanist/' directory.
  $arcanist_dir = dirname(dirname(__FILE__));

  // The parent directory of 'arcanist/'.
  $parent_dir = dirname($arcanist_dir);

  // The 'arcanist/externals/includes/' directory.
  $include_dir = implode(
    DIRECTORY_SEPARATOR,
    array(
      $arcanist_dir,
      'externals',
      'includes',
    ));

  $php_include_path = ini_get('include_path');
  $php_include_path = implode(
    PATH_SEPARATOR,
    array(
      $parent_dir,
      $php_include_path,
      $include_dir,
    ));

  ini_set('include_path', $php_include_path);
}
arcanist_adjust_php_include_path();

if (getenv('ARC_PHUTIL_PATH')) {
  @include_once getenv('ARC_PHUTIL_PATH').'/scripts/__init_script__.php';
} else {
  @include_once 'libphutil/scripts/__init_script__.php';
}
if (!@constant('__LIBPHUTIL__')) {
  echo "ERROR: Unable to load libphutil. Put libphutil/ next to arcanist/, or ".
    "update your PHP 'include_path' to include the parent directory of ".
    "libphutil/, or symlink libphutil/ into arcanist/externals/includes/.\n";
  exit(1);
}

phutil_load_library(dirname(dirname(__FILE__)).'/src/');

PhutilTranslator::getInstance()
  ->setLocale(PhutilLocale::loadLocale('en_US'))
  ->setTranslations(PhutilTranslation::getTranslationMapForLocale('en_US'));
