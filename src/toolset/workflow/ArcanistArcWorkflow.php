<?php

abstract class ArcanistArcWorkflow
  extends ArcanistWorkflow {

  public function supportsToolset(ArcanistToolset $toolset) {
    $key = $toolset->getToolsetKey();
    return ($key === ArcanistArcToolset::TOOLSETKEY);
  }

}
