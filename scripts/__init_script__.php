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

@include_once 'libphutil/scripts/__init_script__.php';
if (!@constant('__LIBPHUTIL__')) {
  echo "ERROR: Unable to load libphutil. Put libphutil/ next to arcanist/, or ".
       "update your PHP 'include_path' to include the parent directory of ".
       "libphutil/, or symlink libphutil/ into arcanist/externals/includes/.\n";
  exit(1);
}

PhutilTranslator::getInstance()
  ->addTranslations(array(
    'Locally modified path(s) are not included in this revision:' => array(
      'A locally modified path is not included in this revision:',
      'Locally modified paths are not included in this revision:',
    ),
    'They will NOT be committed. Commit this revision anyway?' => array(
      'It will NOT be committed. Commit this revision anyway?',
      'They will NOT be committed. Commit this revision anyway?',
    ),
    'Revision includes changes to path(s) that do not exist:' => array(
      'Revision includes changes to a path that does not exist:',
      'Revision includes changes to paths that do not exist:',
    ),

    'This diff includes file(s) which are not valid UTF-8 (they contain '.
      'invalid byte sequences). You can either stop this workflow and fix '.
      'these files, or continue. If you continue, these files will be '.
      'marked as binary.' => array(
      'This diff includes a file which is not valid UTF-8 (it has invalid '.
        'byte sequences). You can either stop this workflow and fix it, or '.
        'continue. If you continue, this file will be marked as binary.',
      'This diff includes files which are not valid UTF-8 (they contain '.
        'invalid byte sequences). You can either stop this workflow and fix '.
        'these files, or continue. If you continue, these files will be '.
        'marked as binary.',
    ),
    'AFFECTED FILE(S)' => array('AFFECTED FILE', 'AFFECTED FILES'),
    'Do you want to mark these files as binary and continue?' => array(
      'Do you want to mark this file as binary and continue?',
      'Do you want to mark these files as binary and continue?',
    ),

    'line(s)' => array('line', 'lines'),

    '%d test(s)' => array('%d test', '%d tests'),

    '%d assertion(s) passed.' => array(
      '%d assertion passed.',
      '%d assertions passed.',
    ),
  ));

phutil_load_library(dirname(dirname(__FILE__)).'/src/');
