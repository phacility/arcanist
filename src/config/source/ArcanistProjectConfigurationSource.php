<?php

final class ArcanistProjectConfigurationSource
  extends ArcanistWorkingCopyConfigurationSource {

  public function getFileKindDisplayName() {
    return pht('Project Config File');
  }

}
