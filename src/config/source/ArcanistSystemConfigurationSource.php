<?php

final class ArcanistSystemConfigurationSource
  extends ArcanistFilesystemConfigurationSource {

  public function getFileKindDisplayName() {
    return pht('System Config File');
  }

}
