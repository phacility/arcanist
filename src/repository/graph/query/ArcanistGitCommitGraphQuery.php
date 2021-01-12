<?php

final class ArcanistGitCommitGraphQuery
  extends ArcanistCommitGraphQuery {

  private $seen = array();
  private $futures = array();
  private $iterators = array();
  private $cursors = array();
  private $iteratorKey = 0;

  public function execute() {
    $this->newFutures();

    $this->executeIterators();

    return $this->seen;
  }

  private function newFutures() {
    $head_hashes = $this->getHeadHashes();
    $exact_hashes = $this->getExactHashes();

    if (!$head_hashes && !$exact_hashes) {
      throw new Exception(pht('Need head hashes or exact hashes!'));
    }

    $api = $this->getRepositoryAPI();
    $ref_lists = array();

    if ($head_hashes) {
      $refs = array();
      if ($head_hashes !== null) {
        foreach ($head_hashes as $hash) {
          $refs[] = $hash;
        }
      }

      $tail_hashes = $this->getTailHashes();
      if ($tail_hashes !== null) {
        foreach ($tail_hashes as $tail_hash) {
          $refs[] = sprintf('^%s^@', $tail_hash);
        }
      }

      $ref_lists[] = $refs;
    }

    if ($exact_hashes !== null) {
      foreach ($exact_hashes as $exact_hash) {
        $ref_list = array();
        $ref_list[] = $exact_hash;
        $ref_list[] = sprintf('^%s^@', $exact_hash);
        $ref_list[] = '--';
        $ref_lists[] = $ref_list;
      }
    }

    $flags = array();

    $min_epoch = $this->getMinimumEpoch();
    if ($min_epoch !== null) {
      $flags[] = '--after';
      $flags[] = date('c', $min_epoch);
    }

    $max_epoch = $this->getMaximumEpoch();
    if ($max_epoch !== null) {
      $flags[] = '--before';
      $flags[] = date('c', $max_epoch);
    }

    foreach ($ref_lists as $ref_list) {
      $ref_blob = implode("\n", $ref_list)."\n";

      $fields = array(
        '%e',
        '%H',
        '%P',
        '%ct',
        '%B',
      );

      $format = implode('%x02', $fields).'%x01';

      $future = $api->newFuture(
        'log --format=%s %Ls --stdin --',
        $format,
        $flags);
      $future->write($ref_blob);
      $future->setResolveOnError(true);

      $this->futures[] = $future;
    }
  }

  private function executeIterators() {
    while ($this->futures || $this->iterators) {
      $iterator_limit = 8;

      while (count($this->iterators) < $iterator_limit) {
        if (!$this->futures) {
          break;
        }

        $future = array_pop($this->futures);
        $future->start();

        $iterator = id(new LinesOfALargeExecFuture($future))
          ->setDelimiter("\1");
        $iterator->rewind();

        $iterator_key = $this->getNextIteratorKey();
        $this->iterators[$iterator_key] = $iterator;
      }

      $limit = $this->getLimit();

      foreach ($this->iterators as $iterator_key => $iterator) {
        $this->executeIterator($iterator_key, $iterator);

        if ($limit) {
          if (count($this->seen) >= $limit) {
            return;
          }
        }
      }
    }
  }

  private function getNextIteratorKey() {
    return $this->iteratorKey++;
  }

  private function executeIterator($iterator_key, $lines) {
    $graph = $this->getGraph();
    $limit = $this->getLimit();

    $is_done = false;

    while (true) {
      if (!$lines->valid()) {
        $is_done = true;
        break;
      }

      $line = $lines->current();
      $lines->next();

      if ($line === "\n") {
        continue;
      }

      $fields = explode("\2", $line);

      if (count($fields) !== 5) {
        throw new Exception(
          pht(
            'Failed to split line "%s" from "git log".',
            $line));
      }

      list($encoding, $hash, $parents, $commit_epoch, $message) = $fields;

      // TODO: Handle encoding, see DiffusionLowLevelCommitQuery.

      $node = $graph->getNode($hash);
      if (!$node) {
        $node = $graph->newNode($hash);
      }

      $this->seen[$hash] = $node;

      $node
        ->setCommitMessage($message)
        ->setCommitEpoch((int)$commit_epoch);

      if (strlen($parents)) {
        $parents = explode(' ', $parents);

        $parent_nodes = array();
        foreach ($parents as $parent) {
          $parent_node = $graph->getNode($parent);
          if (!$parent_node) {
            $parent_node = $graph->newNode($parent);
          }

          $parent_nodes[$parent] = $parent_node;
          $parent_node->addChildNode($node);

        }
        $node->setParentNodes($parent_nodes);
      } else {
        $parents = array();
      }

      if ($limit) {
        if (count($this->seen) >= $limit) {
          break;
        }
      }
    }

    if ($is_done) {
      unset($this->iterators[$iterator_key]);
    }
  }

}
