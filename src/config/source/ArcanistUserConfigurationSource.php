<?php

final class ArcanistUserConfigurationSource
  extends ArcanistFilesystemConfigurationSource {

  public function getFileKindDisplayName() {
    return pht('User Config File');
  }

  public function isWritableConfigurationSource() {
    return true;
  }

  public function getConfigurationSourceScope() {
    return ArcanistConfigurationSource::SCOPE_USER;
  }

  protected function didReadFilesystemValues(array $values) {
    // Before toolsets, the "~/.arcrc" file had separate top-level keys for
    // "config", "hosts", and "aliases". Transform this older file format into
    // a more modern format.

    if (!isset($values['config'])) {
      // This isn't an older file, so just return the values unmodified.
      return $values;
    }

    // Make the keys in "config" top-level keys. Then add in whatever other
    // top level keys exist, other than "config", preferring keys that already
    // exist in the "config" dictionary.

    // For example, this older configuration file:
    //
    //  {
    //     "hosts": ...,
    //     "config": {x: ..., y: ...},
    //     "aliases": ...
    //   }
    //
    // ...becomes this modern file:
    //
    //  {
    //    "x": ...,
    //    "y": ...,
    //    "hosts": ...,
    //    "aliases": ...
    //  }

    $result = $values['config'];
    unset($values['config']);
    $result += $values;

    return $result;
  }

}
