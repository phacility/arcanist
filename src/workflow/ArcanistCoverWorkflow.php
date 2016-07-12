<?php

/**
 * Covers your professional reputation by blaming changes to locate reviewers.
 */
final class ArcanistCoverWorkflow extends ArcanistWorkflow {

  public function getWorkflowName() {
    return 'cover';
  }

  public function getCommandSynopses() {
    return phutil_console_format(<<<EOTEXT
      **cover** [--rev __revision__] [__path__ ...]
EOTEXT
      );
  }

  public function getCommandHelp() {
    return phutil_console_format(<<<EOTEXT
          Supports: svn, git, hg
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
        'help'      => pht('Cover changes since a specific revision.'),
        'supports'  => array(
          'git',
          'hg',
        ),
        'nosupport' => array(
          'svn' => pht('cover does not currently support %s in svn.', '--rev'),
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
      $this->parseBaseCommitArgument(array($in_rev));
    }

    $paths = $this->selectPathsForWorkflow(
      $in_paths,
      $in_rev,
      ArcanistRepositoryAPI::FLAG_UNTRACKED |
      ArcanistRepositoryAPI::FLAG_ADDED);

    if (!$paths) {
      throw new ArcanistNoEffectException(
        pht("You're covered, you didn't change anything."));
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
          $line_noun = pht(
            '%s line(s)',
            phutil_count($info['lines']));
          $lines = $this->readableSequenceFromLineNumbers($info['lines']);
          echo "  {$file}: {$line_noun} {$lines}\n";
        }
      }
    } else {
      echo pht(
        "You're covered, your changes didn't touch anyone else's code.\n");
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
