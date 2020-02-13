<?php

final class ArcanistFileConfigurationSource
  extends ArcanistFilesystemConfigurationSource {

  public function getFileKindDisplayName() {
    return pht('Config File');
  }

}
