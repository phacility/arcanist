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
 * Covers your professional reputation by blaming changes to locate reviewers.
 *
 * @group workflow
 */
final class ArcanistCoverWorkflow extends ArcanistBaseWorkflow {

  public function getCommandSynopses() {
    return phutil_console_format(<<<EOTEXT
      **cover** [--rev __revision__] [__path__ ...]
EOTEXT
      );
  }

  public function getCommandHelp() {
    return phutil_console_format(<<<EOTEXT
          Supports: svn, git
          Cover your... professional reputation. Show blame for the lines you
          changed in your working copy (svn) or since some commit (hg, git).
          This will take a minute because blame takes a minute, especially under
          SVN.
EOTEXT
      );
  }

  public function getArguments() {
    return array(
      'rev' => array(
        'param'     => 'revision',
        'help'      => 'Cover changes since a specific revision.',
        'supports'  => array(
          'git',
          'hg',
        ),
        'nosupport' => array(
          'svn' => "cover does not currently support --rev in svn.",
        ),
      ),
      '*' => 'paths',
    );
  }

  public function requiresWorkingCopy() {
    return true;
  }

  public function requiresConduit() {
    return false;
  }

  public function requiresAuthentication() {
    return false;
  }

  public function requiresRepositoryAPI() {
    return true;
  }

  public function run() {

    $repository_api = $this->getRepositoryAPI();

    $in_paths = $this->getArgument('paths');
    $in_rev = $this->getArgument('rev');

    if ($in_rev) {
      // Although selectPathsForWorkflow() may set this, we want to set it
      // explicitly so we blame against the correct relative commit.
      $repository_api->parseRelativeLocalCommit(array($in_rev));
    }

    $paths = $this->selectPathsForWorkflow(
      $in_paths,
      $in_rev,
      ArcanistRepositoryAPI::FLAG_UNTRACKED |
      ArcanistRepositoryAPI::FLAG_ADDED);

    if (!$paths) {
      throw new ArcanistNoEffectException(
        "You're covered, you didn't change anything.");
    }

    $covers = array();
    foreach ($paths as $path) {
      if (is_dir($repository_api->getPath($path))) {
        continue;
      }

      $lines = $this->getChangedLines($path, 'cover');
      if (!$lines) {
        continue;
      }

      $blame = $repository_api->getBlame($path);
      foreach ($lines as $line) {
        list($author, $revision) = idx($blame, $line, array(null, null));
        if (!$author) {
          continue;
        }
        if (!isset($covers[$author])) {
          $covers[$author] = array();
        }
        if (!isset($covers[$author][$path])) {
          $covers[$author][$path] = array(
            'lines'     => array(),
            'revisions' => array(),
          );
        }
        $covers[$author][$path]['lines'][] = $line;
        $covers[$author][$path]['revisions'][] = $revision;
      }
    }

    if (count($covers)) {
      foreach ($covers as $author => $files) {
        echo phutil_console_format(
          "**%s**\n",
          $author);
        foreach ($files as $file => $info) {
          $line_noun = pht('line(s)', count($info['lines']));
          $lines = $this->readableSequenceFromLineNumbers($info['lines']);
          echo "  {$file}: {$line_noun} {$lines}\n";
        }
      }
    } else {
      echo "You're covered, your changes didn't touch anyone else's code.\n";
    }

    return 0;
  }

  private function readableSequenceFromLineNumbers(array $array) {
    $sequence = array();
    $last = null;
    $seq  = null;
    $array = array_unique(array_map('intval', $array));
    sort($array);
    foreach ($array as $element) {
      if ($seq !== null && $element == ($seq + 1)) {
        $seq++;
        continue;
      }

      if ($seq === null) {
        $last = $element;
        $seq  = $element;
        continue;
      }

      if ($seq > $last) {
        $sequence[] = $last.'-'.$seq;
      } else {
        $sequence[] = $last;
      }

      $last = $element;
      $seq  = $element;
    }
    if ($last !== null && $seq > $last) {
      $sequence[] = $last.'-'.$seq;
    } else if ($last !== null) {
      $sequence[] = $element;
    }

    return implode(', ', $sequence);
  }

}
