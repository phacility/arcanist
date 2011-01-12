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

class ArcanistSvnHookPreCommitWorkflow extends ArcanistBaseWorkflow {

  public function getCommandHelp() {
    return phutil_console_format(<<<EOTEXT
      **svn-hook-pre-receive** __repository__ __transaction__
          Supports: svn
          You can install this as an SVN pre-commit hook.
EOTEXT
      );
  }

  public function getArguments() {
    return array(
      '*' => 'svnargs',
    );
  }

  public function run() {

    $svnargs = $this->getArgument('svnargs');
    $repository = $svnargs[0];
    $transaction = $svnargs[1];

    list($commit_message) = execx(
      'svnlook log --transaction %s %s',
      $transaction,
      $repository);

    // TODO: Do stuff with commit message.
    var_dump($commit_message);

    list($changed) = execx(
      'svnlook changed --transaction %s %s',
      $transaction,
      $repository);

    $paths = array();
    $changed = explode("\n", trim($changed));
    foreach ($changed as $line) {
      $matches = null;
      preg_match('/^..\s*(.*)$/', $line, $matches);
      $paths[$matches[1]] = strlen($matches[1]);
    }

    $resolved = array();
    $failed = array();
    $missing = array();
    $found = array();
    asort($paths);

    foreach ($paths as $path => $length) {
      foreach ($resolved as $rpath => $root) {
        if (!strncmp($path, $rpath, strlen($rpath))) {
          $resolved[$path] = $root;
          continue 2;
        }
      }
      $config = $path;

      if (basename($config) == '.arcconfig') {
        $resolved[$config] = $config;
        continue;
      }

      $config = rtrim($config, '/');
      $last_config = $config;
      do {
        if (!empty($missing[$config])) {
          break;
        } else if (!empty($found[$config])) {
          $resolved[$path] = $found[$config];
          break;
        }
        list($err) = exec_manual(
          'svnlook cat --transaction %s %s %s',
          $transaction,
          $repository,
          $config ? $config.'/.arcconfig' : '.arcconfig');
        if ($err) {
          $missing[$path] = true;
        } else {
          $resolved[$path] = $config ? $config.'/.arcconfig' : '.arcconfig';
          $found[$config] = $resolved[$path];
        }
        $config = dirname($config);
        if ($config == '.') {
          $config = '';
        }
        if ($config == $last_config) {
          break;
        }
        $last_config = $config;
      } while (true);

      if (empty($resolved[$path])) {
        $failed[] = $path;
      }
    }

    if ($failed && $resolved) {
      $failed_paths = '        '.implode("\n        ", $failed);
      $resolved_paths = '        '.implode("\n        ", array_keys($resolved));
      throw new ArcanistUsageException(
        "This commit includes a mixture of files in Arcanist projects and ".
        "outside of Arcanist projects. A commit which affects an Arcanist ".
        "project must affect only that project.\n\n".
        "Files in projects:\n\n".
        $resolved_paths."\n\n".
        "Files not in projects:\n\n".
        $failed_paths);
    }

    if (!$resolved) {
      // None of the affected paths are beneath a .arcconfig file.
      return 3;
    }

    $groups = array();
    foreach ($resolved as $path => $project) {
      $groups[$project][] = $path;
    }
    if (count($groups) > 1) {
      $message = array();
      foreach ($groups as $config => $group) {
        $message[] = "Files underneath '{$config}':\n\n";
        $message[] = "        ".implode("\n        ", $group)."\n\n";
      }
      $message = implode('', $message);
      throw new ArcanistUsageException(
        "This commit includes a mixture of files from different Arcanist ".
        "projects. A commit which affects an Arcanist project must affect ".
        "only that project.\n\n".
        $message);
    }

    $project_root = key($groups);
    $paths = reset($groups);

    $data = array();
    foreach ($paths as $path) {
      list($err, $filedata) = exec_manual(
        'svnlook cat --transaction %s %s %s',
        $transaction,
        $repository,
        $path);
      $data[$path] = $err ? null : $filedata;
    }

    // TODO: Do stuff with data.
    var_dump($data);

    return 1;
  }
}
