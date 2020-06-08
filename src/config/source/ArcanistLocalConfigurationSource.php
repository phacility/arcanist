<?php

final class ArcanistLocalConfigurationSource
  extends ArcanistWorkingCopyConfigurationSource {

  public function getFileKindDisplayName() {
    return pht('Local Config File');
  }

  public function isWritableConfigurationSource() {
    return true;
  }

  public function getConfigurationSourceScope() {
    return ArcanistConfigurationSource::SCOPE_WORKING_COPY;
  }

}
