<?php

abstract class PhageWorkflow
  extends ArcanistWorkflow {

  public function supportsToolset(ArcanistToolset $toolset) {
    $key = $toolset->getToolsetKey();
    return ($key === PhageToolset::TOOLSETKEY);
  }

}
