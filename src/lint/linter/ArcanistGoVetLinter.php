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

  public function getLinterConfigurationOptions() {
    // govet used to be a linter with options. noop defaults to no options.
    // Restore by using the external linter superclass's default.
    return ArcanistExternalLinter::getLinterConfigurationOptions();
  }
}
