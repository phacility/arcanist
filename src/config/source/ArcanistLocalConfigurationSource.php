<?php

final class ArcanistLocalConfigurationSource
  extends ArcanistWorkingCopyConfigurationSource {

  public function getFileKindDisplayName() {
    return pht('Local Config File');
  }

}