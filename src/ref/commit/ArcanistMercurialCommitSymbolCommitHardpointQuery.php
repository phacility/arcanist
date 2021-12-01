<?php

final class ArcanistMercurialCommitSymbolCommitHardpointQuery
  extends ArcanistWorkflowMercurialHardpointQuery {

  public function getHardpoints() {
    return array(
      ArcanistCommitSymbolRef::HARDPOINT_OBJECT,
    );
  }

  protected function canLoadRef(ArcanistRef $ref) {
    return ($ref instanceof ArcanistCommitSymbolRef);
  }

  public function loadHardpoint(array $refs, $hardpoint) {
    $symbol_map = array();
    foreach ($refs as $key => $ref) {
      $symbol_map[$key] = $ref->getSymbol();
    }

    $symbol_set = array_fuse($symbol_map);
    foreach ($symbol_set as $symbol) {
      $this->validateSymbol($symbol);
    }

    $api = $this->getRepositoryAPI();

    // Using "hg log" with repeated "--rev arguments will have the following
    // behaviors which need accounted for:
    // 1. If any one revision is invalid then the entire command will fail. To
    //    work around this the revset uses a trick where specifying a pattern
    //    for the bookmark() or tag() predicates instead of a literal won't
    //    result in failure if the pattern isn't found.
    // 2. Multiple markers that resolve to the same node will only be included
    //    once in the output. Because of this the order of output can't be
    //    relied upon to match up with the requested symbol. To work around
    //    this, the template used must also output any associated symbols to
    //    match back to. Because of this there is no reasonable way to resolve
    //    symbols with Mercurial-supported modifiers such as 'symbol^'.
    // 3. The working directory can't be identified directly, instead a special
    //    template conditional is used to include 'CWD' as the second item in
    //    the output if the node is also the working directory, or 'NOTCWD'
    //    otherwise. This needs included before the tags/bookmarks in order to
    //    distinguish it from some repository using that same name for a tag or
    //    bookmark.

    $pattern = array();
    $arguments = array();

    $pattern[] = 'log';

    $pattern[] = '--template %s';
    $arguments[] = "{rev}\1".
                   "{node}\1".
                   "{ifcontains(rev, revset('parents()'), 'CWD', 'NOTCWD')}\1".
                   "{tags % '{tag}\2'}{bookmarks % '{bookmark}\2'}\3";

    foreach ($symbol_set as $symbol) {
      // This is the one symbol that wouldn't be a bookmark or tag
      if ($symbol === '.') {
        $pattern[] = '--rev .';
        continue;
      }

      $predicates = array();

      if (ctype_xdigit($symbol)) {
        // Commit hashes are 40 characters
        if (strlen($symbol) <= 40) {
          $predicates[] = hgsprintf('id("%s")', $symbol);
        }
      }

      if (ctype_digit($symbol)) {
        // This is 2^32-1 which is (typically) the maximum size of an int in
        // Python -- passing anything higher than this to rev() will result
        // in a Python exception.
        if ($symbol <= 2147483647) {
          $predicates[] = hgsprintf('rev("%s")', $symbol);
        }
      } else {
        // Mercurial disallows using numbers as marker names.
        $re_symbol = preg_quote($symbol);
        $predicates[] = hgsprintf('bookmark("re:^%s$")', $re_symbol);
        $predicates[] = hgsprintf('tag("re:^%s$")', $re_symbol);
      }

      $pattern[] = '--rev %s';
      $arguments[] = implode(' or ', $predicates);
    }

    $pattern = implode(' ', $pattern);
    array_unshift($arguments, $pattern);

    $future = call_user_func_array(
      array($api, 'newFuture'),
      $arguments);

    list($stdout) = (yield $this->yieldFuture($future));

    $lines = explode("\3", $stdout);

    $hash_map = array();
    $node_list = array();

    foreach ($lines as $line) {
      $parts = explode("\1", $line, 4);

      if (empty(array_filter($parts))) {
        continue;
      } else if (count($parts) === 3) {
        list($rev, $node, $cwd) = $parts;
        $markers = array();
      } else if (count($parts) === 4) {
        list($rev, $node, $cwd, $markers) = $parts;
        $markers = array_filter(explode("\2", $markers));
      } else {
        throw new Exception(
          pht('Execution of "hg log" emitted an unexpected line ("%s").',
            $line));
      }

      $node_list[] = $node;

      if (in_array($rev, $symbol_set)) {
        if (!isset($hash_map[$rev])) {
          $hash_map[$rev] = $node;
        } else if ($hash_map[$rev] !== $node) {
          $hash_map[$rev] = '';
        }
      }

      foreach ($markers as $marker) {
        if (!isset($hash_map[$marker])) {
          $hash_map[$marker] = $node;
        } else if ($hash_map[$marker] !== $node) {
          $hash_map[$marker] = '';
        }
      }

      // The log template will mark the working directory node with 'CWD' which
      // we insert for the special marker '.' for the working directory, used
      // by ArcanistMercurialAPI::newCurrentCommitSymbol().
      if ($cwd === 'CWD') {
        if (!isset($hash_map['.'])) {
          $hash_map['.'] = $node;
        } else if ($hash_map['.'] !== $node) {
          $hash_map['.'] = '';
        }
      }
    }

    // Changeset hashes can be prefixes but also collide with other markers.
    // Consider 'cafe' which could be a bookmark or also a changeset hash
    // prefix. Mercurial will always allow markers to take precedence over
    // changeset hashes when resolving, so only populate symbols that match
    // hashes after all other entries are populated, to avoid the hash taing
    // a spot which a marker might match.
    foreach ($node_list as $node) {
      foreach ($symbol_set as $symbol) {
        if (strncmp($node, $symbol, strlen($symbol)) === 0) {
          if (!isset($hash_map[$symbol])) {
            $hash_map[$symbol] = $node;
          }
        }
      }
    }

    // Remove entries resulting in collisions, which set empty string values
    $hash_map = array_filter($hash_map);

    $results = array();
    foreach ($symbol_map as $key => $symbol) {
      if (isset($hash_map[$symbol])) {
        $results[$key] = $hash_map[$symbol];
      }
    }

    foreach ($results as $key => $result) {
      if ($result === null) {
        continue;
      }

      $ref = id(new ArcanistCommitRef())
        ->setCommitHash($result);

      $results[$key] = $ref;
    }

    yield $this->yieldMap($results);
  }

  private function validateSymbol($symbol) {
    if (strpos($symbol, "\n") !== false) {
      throw new Exception(
        pht(
          'Commit symbol "%s" contains a newline. This is not a valid '.
          'character in a Mercurial commit symbol.',
          addcslashes($symbol, "\\\n")));
    }
  }

}
