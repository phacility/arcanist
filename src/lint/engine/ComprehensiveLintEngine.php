<?php

/**
 * @deprecated
 */
final class ComprehensiveLintEngine extends ArcanistComprehensiveLintEngine {

  public function buildLinters() {
    phutil_deprecated(
      __CLASS__,
      'You should use `ArcanistComprehensiveLintEngine` instead.');
    parent::buildLinters();
  }

}
