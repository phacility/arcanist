<?php

final class ArcanistUserConfigurationSource
  extends ArcanistFilesystemConfigurationSource {

  public function getFileKindDisplayName() {
    return pht('User Config File');
  }

}