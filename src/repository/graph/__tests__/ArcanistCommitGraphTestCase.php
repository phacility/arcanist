<?php

final class ArcanistCommitGraphTestCase
  extends PhutilTestCase {

  public function testGraphQuery() {
    $this->assertPartitionCount(
      1,
      pht('Simple Graph'),
      array('D'),
      'A>B B>C C>D');

    $this->assertPartitionCount(
      1,
      pht('Multiple Heads'),
      array('D', 'E'),
      'A>B B>C C>D C>E');

    $this->assertPartitionCount(
      1,
      pht('Disjoint Graph, One Head'),
      array('B'),
      'A>B C>D');

    $this->assertPartitionCount(
      2,
      pht('Disjoint Graph, Two Heads'),
      array('B', 'D'),
      'A>B C>D');

    $this->assertPartitionCount(
      1,
      pht('Complex Graph'),
      array('A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K'),
      'A>B B>C B>D B>E E>F E>G E>H C>H A>I C>I B>J J>K I>K');
  }

  private function assertPartitionCount($expect, $name, $heads, $corpus) {
    $graph = new ArcanistCommitGraph();

    $query = id(new ArcanistSimpleCommitGraphQuery())
      ->setGraph($graph);

    $query->setCorpus($corpus)->execute();

    $partitions = $graph->newPartitionQuery()
      ->withHeads($heads)
      ->execute();

    $this->assertEqual(
      $expect,
      count($partitions),
      pht('Partition Count for "%s"', $name));
  }

}
