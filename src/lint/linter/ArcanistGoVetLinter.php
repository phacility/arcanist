<?php

/**
 * Deprecated
 *
 * The previous ArcanistGoVetLinter can only work with Go v1.11 and older. This
 * linter is disabled until it can be improved or removed.
 */
final class ArcanistGoVetLinter extends ArcanistNoopLinter {
  public function getLinterConfigurationName() {
    return 'govet';
  }
}
