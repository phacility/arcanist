<?php

final class ArcanistCommitSymbolRef
  extends ArcanistSymbolRef {

  public function getRefDisplayName() {
    return pht('Commit Symbol "%s"', $this->getSymbol());
  }

}
