<?php

final class ArcanistGitCommitSymbolCommitHardpointQuery
  extends ArcanistWorkflowGitHardpointQuery {

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

    $symbol_list = implode("\n", $symbol_set);

    $future = $api->newFuture('cat-file --batch-check --')
      ->write($symbol_list);

    list($stdout) = (yield $this->yieldFuture($future));

    $lines = phutil_split_lines($stdout, $retain_endings = false);

    if (count($lines) !== count($symbol_set)) {
      throw new Exception(
        pht(
          'Execution of "git cat-file --batch-check" emitted an unexpected '.
          'number of lines, expected %s but got %s.',
          phutil_count($symbol_set),
          phutil_count($lines)));
    }

    $hash_map = array();

    $pairs = array_combine($symbol_set, $lines);
    foreach ($pairs as $symbol => $line) {
      $parts = explode(' ', $line, 3);

      if (count($parts) < 2) {
        throw new Exception(
          pht(
            'Execution of "git cat-file --batch-check" emitted an '.
            'unexpected line ("%s").',
            $line));
      }

      list($hash, $type) = $parts;

      // NOTE: For now, symbols which map to tags (which, in turn, map to
      // commits) are ignored here.

      if ($type !== 'commit') {
        $hash_map[$symbol] = null;
        continue;
      }

      $hash_map[$symbol] = $hash;
    }

    $results = array();
    foreach ($symbol_map as $key => $symbol) {
      $results[$key] = $hash_map[$symbol];
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
          'character in a Git commit symbol.',
          addcslashes($symbol, "\\\n")));
    }
  }

}
