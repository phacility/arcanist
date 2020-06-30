<?php

final class ArcanistMercurialCommitGraphQuery
  extends ArcanistCommitGraphQuery {

  private $seen = array();
  private $queryFuture;

  public function execute() {
    $this->beginExecute();
    $this->continueExecute();

    return $this->seen;
  }

  protected function beginExecute() {
    $head_hashes = $this->getHeadHashes();
    $exact_hashes = $this->getExactHashes();

    if (!$head_hashes && !$exact_hashes) {
      throw new Exception(pht('Need head hashes or exact hashes!'));
    }

    $api = $this->getRepositoryAPI();

    $revsets = array();
    if ($head_hashes !== null) {
      $revs = array();
      foreach ($head_hashes as $hash) {
        $revs[] = hgsprintf(
          'ancestors(%s)',
          $hash);
      }
      $revsets[] = $this->joinOrRevsets($revs);
    }

    $tail_hashes = $this->getTailHashes();
    if ($tail_hashes !== null) {
      $revs = array();
      foreach ($tail_hashes as $tail_hash) {
        $revs[] = hgsprintf(
          'descendants(%s)',
          $tail_hash);
      }
      $revsets[] = $this->joinOrRevsets($revs);
    }

    if ($revsets) {
      $revsets = array(
        $this->joinAndRevsets($revsets),
      );
    }

    if ($exact_hashes !== null) {
      $revs = array();
      foreach ($exact_hashes as $exact_hash) {
        $revs[] = hgsprintf(
          '%s',
          $exact_hash);
      }
      $revsets[] = $this->joinOrRevsets($revs);
    }

    $revsets = $this->joinOrRevsets($revsets);

    $fields = array(
      '', // Placeholder for "encoding".
      '{node}',
      '{p1node} {p2node}',
      '{date|rfc822date}',
      '{desc|utf8}',
    );

    $template = implode("\2", $fields)."\1";

    $flags = array();

    $min_epoch = $this->getMinimumEpoch();
    $max_epoch = $this->getMaximumEpoch();
    if ($min_epoch !== null || $max_epoch !== null) {
      $flags[] = '--date';

      if ($min_epoch !== null) {
        $min_epoch = date('c', $min_epoch);
      }

      if ($max_epoch !== null) {
        $max_epoch = date('c', $max_epoch);
      }

      if ($min_epoch !== null && $max_epoch !== null) {
        $flags[] = sprintf(
          '%s to %s',
          $min_epoch,
          $max_epoch);
      } else if ($min_epoch) {
        $flags[] = sprintf(
          '>%s',
          $min_epoch);
      } else {
        $flags[] = sprintf(
          '<%s',
          $max_epoch);
      }
    }

    $future = $api->newFuture(
      'log --rev %s --template %s %Ls --',
      $revsets,
      $template,
      $flags);
    $future->setResolveOnError(true);
    $future->start();

    $lines = id(new LinesOfALargeExecFuture($future))
      ->setDelimiter("\1");
    $lines->rewind();

    $this->queryFuture = $lines;
  }

  protected function continueExecute() {
    $graph = $this->getGraph();
    $lines = $this->queryFuture;
    $limit = $this->getLimit();

    $no_parent = str_repeat('0', 40);

    while (true) {
      if (!$lines->valid()) {
        return false;
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

      $node = $graph->getNode($hash);
      if (!$node) {
        $node = $graph->newNode($hash);
      }

      $this->seen[$hash] = $node;

      $node
        ->setCommitMessage($message)
        ->setCommitEpoch((int)strtotime($commit_epoch));

      if (strlen($parents)) {
        $parents = explode(' ', $parents);
        $parent_nodes = array();
        foreach ($parents as $parent) {
          if ($parent === $no_parent) {
            continue;
          }

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
  }

  private function joinOrRevsets(array $revsets) {
    return $this->joinRevsets($revsets, false);
  }

  private function joinAndRevsets(array $revsets) {
    return $this->joinRevsets($revsets, true);
  }

  private function joinRevsets(array $revsets, $is_and) {
    if (!$revsets) {
      return array();
    }

    if (count($revsets) === 1) {
      return head($revsets);
    }

    if ($is_and) {
      return '('.implode(' and ', $revsets).')';
    } else {
      return '('.implode(' or ', $revsets).')';
    }
  }

}
