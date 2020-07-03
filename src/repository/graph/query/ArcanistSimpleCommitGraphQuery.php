<?php

final class ArcanistSimpleCommitGraphQuery
  extends ArcanistCommitGraphQuery {

  private $corpus;

  public function setCorpus($corpus) {
    $this->corpus = $corpus;
    return $this;
  }

  public function getCorpus() {
    return $this->corpus;
  }

  public function execute() {
    $graph = $this->getGraph();
    $corpus = $this->getCorpus();

    $edges = preg_split('(\s+)', trim($corpus));
    foreach ($edges as $edge) {
      $matches = null;
      $ok = preg_match('(^(?P<parent>\S+)>(?P<child>\S+)\z)', $edge, $matches);
      if (!$ok) {
        throw new Exception(
          pht(
            'Failed to match SimpleCommitGraph directive "%s".',
            $edge));
      }

      $parent = $matches['parent'];
      $child = $matches['child'];

      $pnode = $graph->getNode($parent);
      if (!$pnode) {
        $pnode = $graph->newNode($parent);
      }

      $cnode = $graph->getNode($child);
      if (!$cnode) {
        $cnode = $graph->newNode($child);
      }

      $cnode->addParentNode($pnode);
      $pnode->addChildNode($cnode);
    }
  }

}
